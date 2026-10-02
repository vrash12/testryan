<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Contractor\BlockContractor;
use App\Actions\Contractor\ContractorManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminContractorRequest;
use App\Http\Requests\ContractorNoteRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'approval' => ['nullable', 'integer', 'in:0,1,2'],
            'priority' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
            'blocked' => ['nullable', 'boolean'],
            'missing_w9' => ['nullable', 'boolean'],
            'service_ids' => ['nullable', 'array', 'max:50'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:Services,service_id'],
        ]);
        $query = User::query()->with(['accountState', 'services'])->where('is_admin', 0)
            ->withCount(['jobs as completed_jobs_count' => fn ($jobs) => $jobs->where('is_completed', 1)]);
        if (! ($filters['blocked'] ?? false)) {
            $query->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('blocked_at'));
        }
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company_phone', 'like', $like)
                    ->orWhere('phone1', 'like', $like)
                    ->orWhere('phone2', 'like', $like);
            });
        }
        if (isset($filters['approval'])) {
            $query->where('is_approved', (int) $filters['approval']);
        }
        if (($filters['priority'] ?? false)) {
            $query->where('priority', 1);
        }
        if (! ($filters['archived'] ?? false)) {
            $query->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('archived_at'));
        }
        if (($filters['missing_w9'] ?? false)) {
            $query->whereDoesntHave('documents', fn ($documents) => $documents->where('archived', 0)->whereRaw('LOWER(file_type) = ?', ['w9']));
        }
        foreach (array_unique($filters['service_ids'] ?? []) as $serviceId) {
            $query->whereHas('services', fn ($services) => $services->where('Services.service_id', (int) $serviceId));
        }

        return view('admin.contractors.index', [
            'contractors' => $query->orderByDesc('priority')->orderBy('last_name')->orderBy('user_id')->paginate(20)->withQueryString(),
            'services' => Service::query()->orderBy('service_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.contractors.create', ['services' => Service::query()->orderBy('service_name')->get()]);
    }

    public function block(Request $request, User $user, BlockContractor $action): RedirectResponse
    {
        Gate::authorize('block', $user);
        $data = $request->validate(['blocked' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000'], 'confirmed' => ['required', 'accepted']]);
        $action->run($request->user(), $user, (bool) $data['blocked'], $data['reason']);

        return redirect()->route('admin.contractors.show', $user)->with('status', $data['blocked'] ? 'Contractor blocked. Existing sessions are revoked.' : 'Block removed. Approval review is required before work access.');
    }

    public function store(AdminContractorRequest $request, ContractorManagement $management): RedirectResponse
    {
        $contractor = $management->create($request->user(), $request->validated(), $request->file('profile_photo'));

        return redirect()->route('admin.contractors.show', $contractor)->with('status', 'Contractor account created and held for approval.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        abort_if($user->role() !== 'contractor', 404);
        $user->load(['accountState', 'services', 'documents' => fn ($query) => $query->orderByDesc('upload_date'), 'contractorNotes.adminAuthor', 'profileUpload']);

        return view('admin.contractors.show', [
            'contractor' => $user,
            'canPermanentlyDelete' => ! $user->jobs()->exists() && ! $user->jobNotes()->exists() && ! $user->uploadedMedia()->exists() && ! $user->authoredContractorNotes()->exists(),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        abort_if($user->role() !== 'contractor', 404);

        return view('admin.contractors.edit', ['contractor' => $user->load(['accountState', 'services']), 'services' => Service::query()->orderBy('service_name')->get()]);
    }

    public function update(AdminContractorRequest $request, User $user, ContractorManagement $management): RedirectResponse
    {
        abort_if($user->role() !== 'contractor', 404);
        $management->update($request->user(), $user, $request->validated(), $request->file('profile_photo'));

        return redirect()->route('admin.contractors.show', $user)->with('status', 'Contractor profile saved.');
    }

    public function approval(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('approve', $user);
        $data = $request->validate(['approval' => ['required', 'integer', 'in:0,1,2'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $management->setApproval($request->user(), $user, (int) $data['approval'], $data['reason'] ?? null);

        return back()->with('status', 'Contractor approval status updated.');
    }

    public function priority(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort_if($user->role() !== 'contractor', 404);
        $management->setPriority($request->user(), $user, $request->boolean('priority'));

        return back()->with('status', 'Contractor priority updated.');
    }

    public function archive(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $management->archive($request->user(), $user);

        return redirect()->route('admin.contractors.index')->with('status', 'Contractor account archived.');
    }

    public function destroy(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('delete', $user);
        abort_if($user->role() !== 'contractor', 404);

        try {
            $management->delete($request->user(), $user);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.contractors.index')->with('status', 'Contractor account permanently deleted. Activity history was retained.');
    }

    public function restore(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $management->restore($request->user(), $user);

        return back()->with('status', 'Contractor account restored.');
    }

    public function note(ContractorNoteRequest $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('view', $user);
        abort_if($user->role() !== 'contractor', 404);
        $management->addNote($request->user(), $user, $request->validated('note_text'));

        return back()->with('status', 'Private contractor note added.');
    }

    public function removePhoto(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('update', $user);
        abort_if($user->role() !== 'contractor', 404);
        $management->removeProfilePhoto($request->user(), $user);

        return back()->with('status', 'Profile photo removed and account returned to admin review.');
    }
}
