<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Job\JobManagement;
use App\Enums\MediaTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobAssignmentRequest;
use App\Http\Requests\JobMediaRequest;
use App\Http\Requests\JobNoteRequest;
use App\Http\Requests\JobRequest;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobMedia;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $query = Job::query()->with(['contractor', 'services'])->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('entered_datetime');
        $this->filter($query, $request);

        return view('admin.jobs.index', [
            'jobs' => $query->paginate(20)->withQueryString(),
            'counts' => [
                'total' => Job::count(),
                'unassigned' => Job::whereNull('contractor_id')->count(),
                'pending' => Job::whereNotNull('contractor_id')->where('is_accepted', 0)->where('is_completed', 0)->count(),
                'accepted' => Job::where('is_accepted', 1)->where('is_completed', 0)->count(),
                'completed' => Job::where('is_completed', 1)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.jobs.create', $this->formData());
    }

    public function store(JobRequest $request, JobManagement $management): RedirectResponse
    {
        $data = $request->validated();
        $job = $management->create($request->user(), $data);
        if ($request->boolean('accept_on_behalf')) {
            $management->accept($request->user(), $job);
        }

        return redirect()->route('admin.jobs.show', $job)->with('status', 'Job created.');
    }

    public function show(Job $job): View
    {
        Gate::authorize('view', $job);

        return view('jobs.show', $this->showData($job, 'admin'));
    }

    public function edit(Job $job): View
    {
        Gate::authorize('update', $job);

        return view('admin.jobs.edit', $this->formData($job));
    }

    public function update(JobRequest $request, Job $job, JobManagement $management): RedirectResponse
    {
        $management->update($request->user(), $job, $request->validated());

        return redirect()->route('admin.jobs.show', $job)->with('status', 'Job updated.');
    }

    public function assignment(JobAssignmentRequest $request, Job $job, JobManagement $management): RedirectResponse
    {
        $management->assign($request->user(), $job, $request->validated('contractor_id'));

        return back()->with('status', 'Job assignment updated; acceptance and completion were reset.');
    }

    public function acceptance(Job $job, JobManagement $management): RedirectResponse
    {
        Gate::authorize('accept', $job);
        $management->accept(request()->user(), $job);

        return back()->with('status', 'Job accepted on behalf of the assigned contractor.');
    }

    public function completion(Request $request, Job $job, JobManagement $management): RedirectResponse
    {
        Gate::authorize('complete', $job);
        $completed = $request->boolean('completed', true);
        $management->complete($request->user(), $job, $completed);

        return back()->with('status', $completed ? 'Job marked complete.' : 'Job reopened.');
    }

    public function note(JobNoteRequest $request, Job $job, JobManagement $management): RedirectResponse
    {
        $management->addNote($request->user(), $job, $request->validated('note_text'));

        return back()->with('status', 'Job note added.');
    }

    public function media(JobMediaRequest $request, Job $job, JobManagement $management): RedirectResponse|JsonResponse
    {
        $media = $management->addMedia($request->user(), $job, $request->file('media', []), MediaTag::from($request->validated('tag')), $request->validated('upload_id'), $request->validated('upload_group'));
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media saved privately.', 'media_ids' => array_map(fn ($row) => $row->media_id, $media)])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', 'Job media uploaded.');
    }

    public function destroy(Job $job, JobManagement $management): RedirectResponse
    {
        Gate::authorize('delete', $job);
        $management->delete(request()->user(), $job);

        return redirect()->route('admin.jobs.index')->with('status', 'Job deleted.');
    }

    public function destroyMedia(Job $job, JobMedia $media, JobManagement $management): RedirectResponse
    {
        Gate::authorize('delete', $media);
        abort_unless((int) $media->job_id === (int) $job->job_id, 404);
        $management->deleteMedia(request()->user(), $media);

        return back()->with('status', 'Job media deleted.');
    }

    public function print(Job $job): View
    {
        Gate::authorize('view', $job);

        return view('jobs.print', $this->showData($job, 'admin'));
    }

    /** @return array<string, mixed> */
    private function formData(?Job $job = null): array
    {
        return [
            'job' => $job?->load('services'),
            'services' => Service::query()->orderBy('service_name')->get(),
            'templates' => Template::query()->where('type', 'Jobs')->orderBy('name')->get(['template_id', 'name', 'type']),
            'contractors' => User::query()->where('is_admin', 0)->where('is_approved', 1)->whereHas('accountState', fn ($query) => $query->whereNull('blocked_at')->whereNull('archived_at'))->orderByDesc('priority')->orderBy('company_name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function showData(Job $job, string $role): array
    {
        $job->load(['contractor', 'services', 'media.uploader', 'media.privateUpload', 'media.privateFile']);
        $mediaByTag = collect(MediaTag::cases())->mapWithKeys(fn (MediaTag $tag) => [$tag->value => $job->media->where('tag', $tag)->values()]);

        return [
            'job' => $job,
            'role' => $role,
            'notes' => $job->notes()->with('author')->latest('created_datetime')->paginate(10, ['*'], 'notes_page'),
            'mediaByTag' => $mediaByTag,
            'services' => Service::query()->orderBy('service_name')->get(),
            'contractors' => User::query()->where('is_admin', 0)->where('is_approved', 1)->whereHas('accountState', fn ($query) => $query->whereNull('blocked_at')->whereNull('archived_at'))->orderByDesc('priority')->orderBy('company_name')->get(),
            'activity' => ActivityLog::query()->where('action', 'like', '%job #'.$job->job_id.'%')->latest('timestamp')->limit(20)->get(),
        ];
    }

    private function filter($query, Request $request): void
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:open,unassigned,pending,accepted,completed'], 'due' => ['nullable', 'in:overdue,upcoming']]);
        if ($search = trim($data['q'] ?? '')) {
            $query->where(function ($nested) use ($search): void {
                $nested->where('job_name', 'like', '%'.$search.'%')->orWhereHas('contractor', fn ($contractor) => $contractor->where('company_name', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
            });
        }
        match ($data['status'] ?? null) {
            'open' => $query->where('is_completed', 0),
            'unassigned' => $query->whereNull('contractor_id')->where('is_completed', 0),
            'pending' => $query->whereNotNull('contractor_id')->where('is_accepted', 0)->where('is_completed', 0),
            'accepted' => $query->where('is_accepted', 1)->where('is_completed', 0),
            'completed' => $query->where('is_completed', 1),
            default => null,
        };
        if (($data['due'] ?? null) === 'overdue') {
            $query->where('is_completed', 0)->whereDate('due_date', '<', today()->toDateString());
        } elseif (($data['due'] ?? null) === 'upcoming') {
            $query->where('is_completed', 0)->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()]);
        }
    }
}
