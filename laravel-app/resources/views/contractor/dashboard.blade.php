<x-app-layout role="contractor" :navigation="$navigation" title="Dashboard">
    <div class="dashboard-design">
        <section class="dash-hero">
            <div class="relative z-10 max-w-2xl">
                <p class="dash-eyebrow">Your workspace / Contractor</p>
                <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Hello, {{ $contractor?->first_name ?: 'contractor' }}.</h1>
                <p class="mt-3 max-w-lg text-sm leading-7 text-white/80">Your work, all in one place. Keep your next job moving and your documents ready.</p>
                <div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('contractor.jobs.index') }}" class="dash-action">View my jobs <x-icon name="arrow" /></a><a href="{{ route('contractor.documents.index', $contractor) }}" class="dash-action dash-action-light">My documents</a></div>
            </div>
            <div class="dash-hero-stamp" aria-hidden="true"><x-icon name="job" class="size-10" /><span>READY FOR<br>THE NEXT JOB.</span></div>
        </section>

        <section aria-labelledby="contractor-overview" class="mt-8">
            <div class="dash-section-heading"><h2 id="contractor-overview">Your work at a glance</h2><span>Assigned to you</span></div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ([['Open jobs', $metrics['jobs'], 'open', 'job'], ['Awaiting acceptance', $metrics['pending_jobs'], 'pending', 'review'], ['Accepted', $metrics['accepted_jobs'], 'accepted', 'check'], ['Completed', $metrics['completed_jobs'], 'completed', 'status']] as [$label, $value, $filter, $icon])
                    <x-dashboard-metric :label="$label" :value="$value" :icon="$icon" :href="route('contractor.jobs.index', ['status' => $filter])" :tone="$filter === 'pending' && $value > 0 ? 'warm' : 'plain'" hint="View jobs" />
                @endforeach
                <x-dashboard-metric label="Active documents" :value="$metrics['documents']" icon="file" :href="route('contractor.documents.index', $contractor)" hint="View documents" />
                <x-dashboard-metric label="Insurance due" :value="$metrics['expiring_documents']" icon="bell" :href="route('contractor.documents.index', $contractor)" hint="Within 30 days" :tone="$metrics['expiring_documents'] > 0 ? 'warm' : 'plain'" />
            </div>
        </section>

        <section class="dash-next mt-7" aria-labelledby="next-step">
            <span class="dash-step-icon"><x-icon name="arrow" class="size-6" /></span>
            <div class="min-w-0 flex-1"><p class="text-xs font-bold uppercase tracking-widest">Your next step</p><h2 id="next-step" class="mt-1 text-lg font-bold">{{ $nextAction }}</h2><p class="mt-1 text-sm text-stone-700">@if(! $profileComplete)Profile and service changes return your account to admin review.@elseif(! $documentComplete)Keep your W9 and insurance together in your document workspace.@else Check your assigned work for details and upcoming deadlines.@endif</p></div>
            @if(! $profileComplete)<a class="dash-action" href="{{ route('contractor.profile.edit', $contractor) }}">Complete profile <x-icon name="arrow" /></a>@elseif(! $documentComplete)<a class="dash-action" href="{{ route('contractor.documents.index', $contractor) }}">Manage documents <x-icon name="arrow" /></a>@else<a class="dash-action" href="{{ route('contractor.jobs.index') }}">Open jobs <x-icon name="arrow" /></a>@endif
        </section>

        <div class="mt-7 grid items-start gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <x-card>
                <div class="dash-panel-heading"><div><p class="dash-kicker">On your schedule</p><h2>My jobs</h2></div><a class="dash-text-link" href="{{ route('contractor.jobs.index') }}">All jobs <x-icon name="arrow" class="size-4" /></a></div>
                <div class="mt-4">
                    @forelse($jobs as $job)
                        <a href="{{ route('contractor.jobs.show', $job) }}" class="dash-record">
                            <span class="dash-record-icon"><x-icon name="job" /></span>
                            <span class="min-w-0 flex-1"><span class="block break-words font-bold">{{ $job->job_name }}</span><span class="mt-1 block text-sm text-stone-600">{{ ucfirst($job->status()->value) }}</span></span>
                            <span class="text-sm {{ $job->due_date && $job->due_date->lt(today()) && $job->is_completed === 0 ? 'font-bold text-red-700' : 'text-stone-600' }}">{{ $job->due_date?->format('M j, Y') ?: 'No due date' }}</span><x-icon name="arrow" class="size-4" />
                        </a>
                    @empty
                        <x-dashboard-empty icon="job" title="No jobs are assigned to you yet.">Your next assignment will appear here, with its details, due date and acceptance action.</x-dashboard-empty>
                    @endforelse
                </div>
            </x-card>
            <x-card>
                <div class="dash-panel-heading"><div><p class="dash-kicker">Stay ready</p><h2>Profile and documents</h2></div><x-badge :tone="$profileComplete && $documentComplete ? 'success' : 'warning'">{{ $profileComplete && $documentComplete ? 'Ready' : 'Needs attention' }}</x-badge></div>
                <dl class="mt-5">
                    @foreach ([['Profile and services', $profileComplete ? 'Complete' : 'Incomplete', $profileComplete, 'profile'], ['W9 and insurance', $documentComplete ? 'Present' : 'Missing or expired', $documentComplete, 'file'], ['Approval', $contractor?->is_approved === 1 ? 'Approved' : 'Pending review', $contractor?->is_approved === 1, 'status']] as [$label, $state, $ready, $icon])
                        <div class="dash-check-row"><x-icon :name="$icon" /><div class="min-w-0 flex-1"><dt class="text-sm font-semibold">{{ $label }}</dt><dd class="mt-1 text-xs {{ $ready ? 'text-emerald-800' : 'text-amber-900' }}">{{ $state }}</dd></div><span class="dash-check-indicator {{ $ready ? 'is-ready' : '' }}" aria-hidden="true">@if($ready)<x-icon name="check" class="size-4" />@else<span>!</span>@endif</span></div>
                    @endforeach
                </dl>
                <div class="mt-5 flex flex-wrap gap-3"><a class="dash-text-link" href="{{ route('contractor.profile.edit', $contractor) }}">Profile <x-icon name="arrow" class="size-4" /></a><a class="dash-text-link" href="{{ route('contractor.documents.index', $contractor) }}">Documents <x-icon name="arrow" class="size-4" /></a></div>
            </x-card>
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-2">
            @foreach ([['Overdue', 'overdue', $overdueJobs, 'No overdue open jobs.'], ['Due next 7 days', 'upcoming', $upcomingJobs, 'No open jobs due soon.']] as [$label, $filter, $items, $empty])
                <x-card><div class="dash-panel-heading"><h2>{{ $label }}</h2><a class="dash-text-link" href="{{ route('contractor.jobs.index', ['due' => $filter]) }}">View all <x-icon name="arrow" class="size-4" /></a></div>
                    @forelse($items as $job)<a href="{{ route('contractor.jobs.show', $job) }}" class="dash-record"><span class="min-w-0 flex-1 break-words font-semibold">{{ $job->job_name }}</span><span class="shrink-0 text-sm {{ $filter === 'overdue' ? 'text-red-700' : 'text-amber-900' }}">{{ $job->due_date->format('M j, Y') }}</span></a>@empty<x-dashboard-empty :title="$empty">Your schedule is clear for this window.</x-dashboard-empty>@endforelse
                </x-card>
            @endforeach
        </div>
    </div>
</x-app-layout>
