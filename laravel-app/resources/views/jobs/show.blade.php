@php
    $isAdmin = $role === 'admin';
    $statusValue = $job->status()->value;
    $status = match ($statusValue) {
        'completed' => ['Completed', 'success'],
        'accepted' => ['Accepted', 'success'],
        'pending' => ['Pending acceptance', 'warning'],
        default => ['Unassigned', 'neutral'],
    };
    $assigned = $job->contractor !== null;
    $canAddNote = $isAdmin || ($statusValue !== 'completed');
    $canUploadMedia = $isAdmin || ($statusValue === 'accepted');
    $mediaTags = ['Before', 'During', 'After', 'Other'];
    $contractorName = $assigned ? ($job->contractor->company_name ?: $job->contractor->first_name.' '.$job->contractor->last_name) : null;

    $due = null;
    if ($job->due_date) {
        $days = (int) today()->diffInDays($job->due_date, false);
        $due = match (true) {
            $statusValue === 'completed' => ['Due '.$job->due_date->format('M j, Y'), 'text-stone-600'],
            $days < 0 => ['Overdue by '.abs($days).' '.str('day')->plural(abs($days)).' · '.$job->due_date->format('M j, Y'), 'font-bold text-red-700'],
            $days === 0 => ['Due today · '.$job->due_date->format('M j, Y'), 'font-bold text-amber-800'],
            $days <= 7 => ['Due in '.$days.' '.str('day')->plural($days).' · '.$job->due_date->format('M j, Y'), 'font-bold text-amber-800'],
            default => ['Due '.$job->due_date->format('M j, Y'), 'text-stone-700'],
        };
    }

    $accepted = $job->is_accepted === 1 || $statusValue === 'completed';
    $timeline = [
        ['Created', true, $job->entered_datetime?->format('M j, Y g:i A') ?: '—'],
        ['Assigned', $assigned, $contractorName ?: 'Not assigned yet'],
        ['Accepted', $accepted, $accepted ? ($job->accepted_datetime?->format('M j, Y g:i A') ?: 'Recorded') : 'Not yet'],
        ['Completed', $statusValue === 'completed', $job->completed_datetime?->format('M j, Y g:i A') ?: 'Not yet'],
    ];
    $currentStep = collect($timeline)->search(fn ($step) => ! $step[1]);

    $price = $job->approved_price !== null ? (float) $job->approved_price : null;
    $deposit = $job->deposit_amount !== null ? (float) $job->deposit_amount : null;
@endphp
<x-app-layout :role="$role" :navigation="config('nestcrafters.navigation.'. $role)" :title="$job->job_name">
    <x-page-header :eyebrow="$isAdmin ? 'Job management' : 'Assigned work'" :title="$job->job_name" :back="$isAdmin ? route('admin.jobs.index') : route('contractor.jobs.index')" :back-label="$isAdmin ? 'All jobs' : 'My jobs'">
        <x-slot:actions>
            <x-button href="{{ $isAdmin ? route('admin.jobs.print', $job) : route('contractor.jobs.print', $job) }}" variant="secondary"><x-icon name="print" class="size-4" />Print view</x-button>
            @if($isAdmin)<x-button href="{{ route('admin.jobs.edit', $job) }}" variant="secondary">Edit job</x-button>@endif
        </x-slot:actions>
    </x-page-header>
    <div class="record-meta mt-4">
        <x-badge :tone="$status[1]">{{ $status[0] }}</x-badge>
        <span class="inline-flex items-center gap-1.5"><x-icon name="people" class="size-4" />{{ $contractorName ?: 'No contractor assigned' }}</span>
        <span class="inline-flex items-center gap-1.5 {{ $due[1] ?? '' }}"><x-icon name="calendar" class="size-4" />{{ $due[0] ?? 'No due date' }}</span>
    </div>

    @if(! $isAdmin && $statusValue === 'pending')
        <section id="accept-job" class="callout callout-warning mt-6" aria-labelledby="accept-title">
            <span class="dash-step-icon"><x-icon name="check" class="size-6" /></span>
            <div class="min-w-0 flex-1"><h2 id="accept-title" class="text-lg font-black">Review and accept this job</h2><p class="mt-1 text-sm leading-6 text-stone-800">Read the description, terms and financial details below. Accepting confirms you'll take the work and unlocks photo and document uploads.</p></div>
            <form method="POST" action="{{ route('contractor.jobs.acceptance', $job) }}" class="w-full sm:w-auto">@csrf<x-button type="submit" class="w-full">Accept job</x-button></form>
        </section>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,1fr)]">
        <div class="space-y-6">
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-4"><h2 class="text-lg font-black text-brand-700">Job progress</h2><x-badge :tone="$status[1]">{{ $status[0] }}</x-badge></div>
                <ol class="job-timeline mt-6" aria-label="Job progress">
                    @foreach($timeline as [$label, $done, $detail])
                        <li @class(['job-step', 'is-done' => $done, 'is-current' => $currentStep === $loop->index])>
                            <span class="job-step-dot" aria-hidden="true">@if($done)<x-icon name="check" class="size-4" />@else{{ $loop->iteration }}@endif</span>
                            <div class="min-w-0 sm:mt-3"><p class="job-step-label">{{ $label }} <span class="sr-only">{{ $done ? '(done)' : '(not yet)' }}</span></p><p class="job-step-time line-clamp-3 break-words" title="{{ $detail }}">{{ $detail }}</p></div>
                        </li>
                    @endforeach
                </ol>
                @if($isAdmin && (($assigned && $statusValue === 'pending') || in_array($statusValue, ['accepted', 'completed'], true)))
                    <div class="mt-6 flex flex-wrap gap-3 border-t-2 border-ink pt-5">
                        @if($assigned && $statusValue === 'pending')<form method="POST" action="{{ route('admin.jobs.acceptance', $job) }}">@csrf<x-button type="submit">Accept on behalf</x-button></form>@endif
                        @if($statusValue === 'accepted')<form method="POST" action="{{ route('admin.jobs.completion', $job) }}">@csrf<input type="hidden" name="completed" value="1"><x-button type="submit">Mark complete</x-button></form>@elseif($statusValue === 'completed')<form method="POST" action="{{ route('admin.jobs.completion', $job) }}">@csrf<input type="hidden" name="completed" value="0"><x-button type="submit" variant="secondary">Reopen job</x-button></form>@endif
                    </div>
                    @if($assigned && $statusValue === 'pending')<p class="mt-3 text-xs leading-5 text-stone-600">Accepting on behalf records you as the actor and timestamps acceptance for {{ $contractorName }}.</p>@endif
                @elseif($isAdmin && ! $assigned)
                    <p class="mt-6 border-t-2 border-ink pt-5 text-sm text-stone-700">Assign a contractor to send this job for acceptance.</p>
                @endif
            </x-card>

            <x-card><h2 class="text-lg font-black text-brand-700">Description</h2><div class="prose-job mt-4 max-w-none break-words leading-7">{!! \App\Support\JobContent::render($job->job_description) !!}</div></x-card>
            @if($job->terms)<x-card><h2 class="text-lg font-black text-brand-700">Terms</h2><div class="prose-job mt-4 max-w-none break-words leading-7">{!! \App\Support\JobContent::render($job->terms) !!}</div></x-card>@endif

            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-black text-brand-700">Job notes</h2><p class="mt-1 text-sm text-stone-600">Shared with the authorized job participants.</p></div><x-icon name="note" class="size-6 text-brand-700" /></div>
                @if($canAddNote)<form method="POST" action="{{ $isAdmin ? route('admin.jobs.notes.store', $job) : route('contractor.jobs.notes.store', $job) }}" class="mt-5">@csrf<label for="note_text" class="mb-2 block text-sm font-bold">New note</label><textarea id="note_text" name="note_text" rows="4" maxlength="10000" required class="w-full" placeholder="Ask a question or post a progress update"></textarea><x-button type="submit" class="mt-3">Add note</x-button></form>@else<p class="mt-5 rounded-md border-2 border-dashed border-stone-400 bg-stone-50 p-4 text-sm text-stone-700">Completed jobs are read-only for contractors.</p>@endif
                <div class="mt-6 space-y-4">@forelse($notes as $note)<article class="rounded-md border-l-4 border-brand-orange bg-paper p-4"><p class="whitespace-pre-wrap break-words text-sm leading-6">{{ $note->note_text }}</p><p class="mt-2 text-xs font-semibold text-stone-600">{{ $note->author?->first_name }} {{ $note->author?->last_name }} · {{ $note->created_datetime?->format('M j, Y g:i A') }}</p></article>@empty<p class="text-sm text-stone-600">No notes yet.</p>@endforelse</div>
                @if(method_exists($notes, 'links'))<div class="mt-6">{{ $notes->links() }}</div>@endif
            </x-card>

            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black text-brand-700">Job media</h2><p class="mt-1 text-sm text-stone-600">JPEG, PNG and PDF files up to 10 MB each.</p></div><x-badge>{{ $job->media->count() }} total</x-badge></div>
                @if($canUploadMedia)
                    <form method="POST" action="{{ $isAdmin ? route('admin.jobs.media.store', $job) : route('contractor.jobs.media.store', $job) }}" enctype="multipart/form-data" data-async-media class="mt-5 grid gap-4 rounded-md border-2 border-ink bg-paper p-4 sm:grid-cols-[minmax(8rem,auto)_minmax(0,1fr)] sm:items-end">@csrf<div><label for="tag" class="mb-2 block text-sm font-bold">Tag</label><select id="tag" name="tag" class="w-full">@foreach($mediaTags as $tag)<option value="{{ $tag }}">{{ $tag }}</option>@endforeach</select></div><x-upload-picker name="media[]" label="Files" multiple /><x-button type="submit" class="sm:col-span-2 sm:justify-self-start"><x-icon name="upload" class="size-4" />Upload / retry remaining</x-button><a hidden data-refresh-media href="{{ route($isAdmin ? 'admin.jobs.show' : 'contractor.jobs.show', $job) }}" class="text-link sm:col-span-2">Refresh to view saved media</a></form>
                @elseif(!$isAdmin)
                    <div class="callout callout-info mt-5 shadow-none"><x-icon name="lock" class="size-6" /><p class="min-w-0 flex-1 text-sm leading-6">{{ $statusValue === 'completed' ? 'This job is completed, so new media can no longer be added.' : 'Accept the job before uploading work media.' }}</p>@if($statusValue === 'pending')<a href="#accept-job" class="text-link">Go to Accept job</a>@endif</div>
                @endif
                <div class="mt-7 space-y-7">
                    @foreach($mediaTags as $tag)
                        @php($mediaItems = $mediaByTag[$tag] ?? collect())
                        <section><h3 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-brand-700">{{ $tag }} <span class="rounded border border-ink bg-mist px-1.5 text-xs">{{ method_exists($mediaItems, 'total') ? $mediaItems->total() : $mediaItems->count() }}</span></h3><div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse($mediaItems as $media)
                                <div class="overflow-hidden rounded-md border-2 border-ink bg-white"><div class="aspect-[4/3] border-b-2 border-ink bg-mist">@if(str_starts_with((string) $media->mime_type, 'image/'))<a href="{{ route('job-media.preview', $media) }}" target="_blank" rel="noreferrer"><img src="{{ route('job-media.thumbnail', $media) }}" alt="{{ $media->file_name }}" class="size-full object-contain" loading="lazy"></a>@else<div class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-brand-700"><x-icon name="file" class="size-8" /><span class="text-xs font-bold uppercase">PDF</span></div>@endif</div><div class="p-3"><p class="break-words text-sm font-bold">{{ $media->file_name }}</p><p class="mt-1 text-xs text-stone-600">{{ $media->uploader?->first_name }} · {{ $media->upload_datetime?->format('M j, Y') }}</p><div class="mt-2 flex flex-wrap gap-x-4"><a class="text-link" href="{{ route('job-media.preview', $media) }}" target="_blank" rel="noreferrer">Preview</a><a class="text-link" href="{{ route('job-media.download', $media) }}">Download</a>@if($isAdmin)<form method="POST" action="{{ route('admin.jobs.media.destroy', [$job, $media]) }}" onsubmit="return confirm('Delete this media file?');">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-11 items-center text-sm font-bold text-red-700 underline underline-offset-4">Delete</button></form>@endif</div></div></div>
                            @empty<p class="text-sm text-stone-600 sm:col-span-2 lg:col-span-3">No {{ strtolower($tag) }} media.</p>@endforelse
                        </div>@if(method_exists($mediaItems, 'links'))<div class="mt-3">{{ $mediaItems->links() }}</div>@endif</section>
                    @endforeach
                </div>
            </x-card>
        </div>

        <aside class="space-y-6">
            @if($isAdmin)
                <x-card><h2 class="text-lg font-black text-brand-700">Assignment</h2><form method="POST" action="{{ route('admin.jobs.assignment', $job) }}" class="mt-4 space-y-4">@csrf<div><label for="contractor_id" class="mb-2 block text-sm font-bold">Contractor</label><select id="contractor_id" name="contractor_id" class="w-full"><option value="">Unassigned</option>@foreach($contractors as $contractor)<option value="{{ $contractor->user_id }}" @selected((int) $job->contractor_id === (int) $contractor->user_id)>{{ $contractor->company_name ?: $contractor->first_name.' '.$contractor->last_name }}{{ $contractor->priority ? ' · Priority' : '' }}</option>@endforeach</select></div><x-button type="submit" variant="dark">Save assignment</x-button><p class="text-xs leading-5 text-stone-600">Changing the contractor resets active acceptance and completion timestamps.</p></form></x-card>
            @endif
            <x-card>
                <h2 class="flex items-center gap-2 text-lg font-black text-brand-700"><x-icon name="money" />Financial details</h2>
                <dl class="mt-4 divide-y-2 divide-mist text-sm">
                    <div class="flex justify-between gap-4 py-3"><dt class="text-stone-600">Approved price</dt><dd class="font-black tabular-nums">{{ $price !== null ? number_format($price, 2) : 'Not set' }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-stone-600">Deposit</dt><dd class="font-bold tabular-nums">{{ $deposit !== null ? number_format($deposit, 2) : 'Not set' }}</dd></div>
                    @if($price !== null && $deposit !== null)<div class="flex justify-between gap-4 py-3"><dt class="text-stone-600">Balance after deposit</dt><dd class="font-bold tabular-nums">{{ number_format($price - $deposit, 2) }}</dd></div>@endif
                    <div class="flex justify-between gap-4 py-3"><dt class="text-stone-600">Materials supplied by</dt><dd class="text-right font-bold">{{ $job->who_supplies_materials ?: 'Not specified' }}</dd></div>
                </dl>
            </x-card>
            <x-card><h2 class="text-lg font-black text-brand-700">Services</h2><div class="mt-4 flex flex-wrap gap-2">@forelse($job->services as $service)<x-badge>{{ $service->service_name }}</x-badge>@empty<p class="text-sm text-stone-600">No services recorded.</p>@endforelse</div></x-card>
            @if($isAdmin)
                <x-card><h2 class="text-lg font-black text-brand-700">Recent activity</h2><div class="mt-4 space-y-3">@forelse($activity as $log)<div class="dash-activity-entry border-t border-stone-200 pt-3"><p class="text-sm leading-6">{{ $log->action }}</p><p class="mt-1 text-xs text-stone-600">{{ $log->timestamp?->format('M j, Y g:i A') }}</p></div>@empty<p class="text-sm text-stone-600">No job activity yet.</p>@endforelse</div></x-card>
                <section class="callout callout-danger block" aria-labelledby="danger-title">
                    <h2 id="danger-title" class="flex items-center gap-2 text-base font-black text-red-900"><x-icon name="alert" />Delete job</h2>
                    <p class="mt-2 text-sm leading-6 text-red-950">Permanently removes the job with its notes, services and uploaded media. Jobs that hold imported media are kept as historical evidence. Transition history is retained.</p>
                    <form method="POST" action="{{ route('admin.jobs.destroy', $job) }}" onsubmit="return confirm('Delete this job and its notes, services and application-created media?');" class="mt-4">@csrf @method('DELETE')<x-button type="submit" variant="danger"><x-icon name="trash" class="size-4" />Delete job</x-button></form>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
