<?php

namespace App\Http\Controllers\Contractor;

use App\Actions\Job\JobManagement;
use App\Enums\MediaTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobMediaRequest;
use App\Http\Requests\JobNoteRequest;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:open,pending,accepted,completed'], 'due' => ['nullable', 'in:overdue,upcoming']]);
        $query = $request->user()->jobs()->with('services');
        if ($q = trim($data['q'] ?? '')) {
            $query->where('job_name', 'like', '%'.addcslashes($q, '%_\\').'%');
        }
        match ($data['status'] ?? null) {
            'open' => $query->where('is_completed', 0),
            'pending' => $query->where('is_accepted', 0)->where('is_completed', 0),
            'accepted' => $query->where('is_accepted', 1)->where('is_completed', 0),
            'completed' => $query->where('is_completed', 1),
            default => null,
        };
        if (($data['due'] ?? null) === 'overdue') {
            $query->where('is_completed', 0)->whereDate('due_date', '<', today()->toDateString());
        } elseif (($data['due'] ?? null) === 'upcoming') {
            $query->where('is_completed', 0)->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()]);
        }
        $jobs = $query->orderBy('is_completed')->orderBy('is_accepted')->orderByRaw('due_date IS NULL')->orderBy('due_date')->paginate(20)->withQueryString();

        return view('contractor.jobs.index', ['jobs' => $jobs]);
    }

    public function show(Job $job): View
    {
        Gate::authorize('view', $job);
        $job->load(['contractor', 'services', 'media.uploader', 'media.privateUpload', 'media.privateFile']);
        $mediaByTag = collect(MediaTag::cases())->mapWithKeys(fn (MediaTag $tag) => [$tag->value => $job->media->where('tag', $tag)->values()]);

        return view('jobs.show', [
            'job' => $job,
            'role' => 'contractor',
            'notes' => $job->notes()->with('author')->latest('created_datetime')->paginate(10, ['*'], 'notes_page'),
            'mediaByTag' => $mediaByTag,
            'services' => collect(),
            'contractors' => collect(),
            'activity' => collect(),
        ]);
    }

    public function accept(Job $job, JobManagement $management): RedirectResponse
    {
        Gate::authorize('accept', $job);
        $management->accept(request()->user(), $job);

        return back()->with('status', 'Job accepted.');
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

    public function print(Job $job): View
    {
        Gate::authorize('view', $job);
        $job->load(['contractor', 'services', 'notes.author']);

        return view('jobs.print', ['job' => $job, 'role' => 'contractor', 'mediaByTag' => collect(), 'notes' => $job->notes, 'activity' => collect(), 'services' => collect(), 'contractors' => collect()]);
    }
}
