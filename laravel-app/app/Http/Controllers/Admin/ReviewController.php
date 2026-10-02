<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Contractor\BlockContractor;
use App\Actions\Contractor\ContractorManagement;
use App\Http\Controllers\Controller;
use App\Models\AccountState;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $this->filters($request, true);

        return view('admin.review.index', [
            'contractors' => $this->filteredQuery($filters, true)->orderByDesc('priority')->orderBy('created_at')->orderBy('user_id')->paginate(20)->withQueryString(),
            'services' => Service::query()->orderBy('service_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function bulk(Request $request, ContractorManagement $management): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);
        $data = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject,archive,block'],
            'contractor_ids' => ['required', 'array', 'min:1', 'max:50'],
            'contractor_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'confirmed' => ['required', 'accepted'],
            'reason' => ['required_if:action,block', 'nullable', 'string', 'max:1000'],
        ]);
        $ids = array_map('intval', $data['contractor_ids']);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['contractor_ids' => 'Select each contractor only once.']);
        }
        sort($ids);

        DB::transaction(function () use ($ids, $data, $request, $management): void {
            // Validate the entire explicit selection before changing any account.
            // User locks also serialize approval, archive and profile changes.
            $selected = User::query()->whereIn('user_id', $ids)->orderBy('user_id')->lockForUpdate()->get();
            $states = AccountState::query()->whereIn('user_id', $ids)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            if ($selected->count() !== count($ids)) {
                throw ValidationException::withMessages(['contractor_ids' => 'The selection contains a missing account. Reload the review queue.']);
            }
            foreach ($selected as $contractor) {
                $contractor->setRelation('accountState', $states->get($contractor->user_id));
                if ($contractor->role() !== 'contractor' || ! $contractor->isActiveAccount() || ! in_array($contractor->is_approved, [0, 2], true)) {
                    throw ValidationException::withMessages(['contractor_ids' => 'An account is no longer eligible for review. Reload the queue; no selected account was changed.']);
                }
                Gate::authorize(match ($data['action']) {
                    'archive' => 'delete', 'block' => 'block', default => 'approve'
                }, $contractor);
            }
            foreach ($selected as $contractor) {
                if ($data['action'] === 'block') {
                    app(BlockContractor::class)->run($request->user(), $contractor, true, $data['reason']);
                } elseif ($data['action'] === 'archive') {
                    $management->archive($request->user(), $contractor);
                } else {
                    $management->setApproval($request->user(), $contractor, $data['action'] === 'approve' ? 1 : 0, $data['reason'] ?? null);
                }
            }
        });

        $verb = match ($data['action']) {
            'approve' => 'approved',
            'reject' => 'set to not approved',
            'archive' => 'archived',
            'block' => 'blocked',
        };

        return redirect()->route('admin.review.index')->with('status', count($ids).' selected contractor account(s) '.$verb.'.');
    }

    public function print(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $this->filters($request, false);
        $query = $this->filteredQuery($filters, false);

        return view('admin.contractors.print', [
            'contractors' => (clone $query)->withCount(['jobs as completed_jobs_count' => fn ($jobs) => $jobs->where('is_completed', 1)])->orderByDesc('priority')->orderBy('last_name')->orderBy('user_id')->limit(1000)->get(),
            'total' => $query->count(),
            'filters' => $filters,
            'selectedServices' => Service::query()->whereIn('service_id', $filters['service_ids'] ?? [])->orderBy('service_name')->pluck('service_name'),
        ]);
    }

    private function filters(Request $request, bool $review): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'approval' => ['nullable', 'integer', $review ? 'in:0,2' : 'in:0,1,2'],
            'priority' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
            'blocked' => ['nullable', 'boolean'],
            'missing_w9' => ['nullable', 'boolean'],
            'service_ids' => ['nullable', 'array', 'max:50'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:Services,service_id'],
        ]);
    }

    private function filteredQuery(array $filters, bool $review): Builder
    {
        $query = User::query()->where('is_admin', 0)
            ->with(['accountState', 'services', 'documents' => fn ($documents) => $documents->select('file_id', 'user_id', 'file_type', 'file_name', 'expiry_date', 'archived')->where('archived', 0)]);
        if ($review) {
            $query->whereIn('is_approved', [0, 2])->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('archived_at')->orWhereNotNull('blocked_at'));
        } else {
            if (! ($filters['blocked'] ?? false)) {
                $query->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('blocked_at'));
            }
            if (! ($filters['archived'] ?? false)) {
                $query->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('archived_at'));
            }
        }
        if ($search = trim($filters['q'] ?? '')) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($searchQuery) use ($like): void {
                $searchQuery->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('company_phone', 'like', $like)->orWhere('phone1', 'like', $like)->orWhere('phone2', 'like', $like);
            });
        }
        if (isset($filters['approval'])) {
            $query->where('is_approved', (int) $filters['approval']);
        }
        if (($filters['priority'] ?? false)) {
            $query->where('priority', 1);
        }
        if (($filters['missing_w9'] ?? false)) {
            $query->whereDoesntHave('documents', fn ($documents) => $documents->where('archived', 0)->whereRaw('LOWER(file_type) = ?', ['w9']));
        }
        // Dashboard matching preserves the legacy ALL selected services behavior.
        foreach (array_unique($filters['service_ids'] ?? []) as $serviceId) {
            $query->whereHas('services', fn ($services) => $services->where('Services.service_id', (int) $serviceId));
        }

        return $query;
    }
}
