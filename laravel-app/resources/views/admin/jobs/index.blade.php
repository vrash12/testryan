<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Jobs">
    <x-page-header eyebrow="Work" title="Jobs">
        Manage assignments, acceptance, deadlines and job evidence.
        <x-slot:actions><x-button href="{{ route('admin.jobs.create') }}"><x-icon name="plus" class="size-4" />Create job</x-button></x-slot:actions>
    </x-page-header>
    <nav aria-label="Job status totals" class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach (['total' => 'All jobs', 'unassigned' => 'Unassigned', 'pending' => 'Pending', 'accepted' => 'Accepted', 'completed' => 'Completed'] as $key => $label)
            @php $active = $key === 'total' ? ! request('status') : request('status') === $key; @endphp
            <a href="{{ route('admin.jobs.index', $key === 'total' ? [] : ['status' => $key]) }}" @if ($active) aria-current="true" @endif @class(['dash-metric', 'col-span-2 sm:col-span-1' => $loop->last, '!bg-brand-700 !text-paper' => $active])><span class="text-xs font-bold uppercase tracking-wide">{{ $label }}</span><span class="mt-2 text-3xl font-black tabular-nums">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>
    <x-card class="mt-6">
        <form method="GET" action="{{ route('admin.jobs.index') }}" class="filter-bar" role="search">
            <x-input name="q" type="search" label="Search jobs" value="{{ request('q') }}" placeholder="Job or contractor name" />
            <div><label for="status" class="mb-2 block text-sm font-bold">Status</label><select id="status" name="status" class="w-full"><option value="">All statuses</option>@foreach (['open' => 'Open jobs', 'unassigned' => 'Unassigned', 'pending' => 'Pending acceptance', 'accepted' => 'Accepted', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="due" class="mb-2 block text-sm font-bold">Due date</label><select id="due" name="due" class="w-full"><option value="">All dates</option><option value="overdue" @selected(request('due') === 'overdue')>Overdue</option><option value="upcoming" @selected(request('due') === 'upcoming')>Next 7 days</option></select></div>
            <div class="filter-actions"><x-button type="submit" variant="dark"><x-icon name="search" class="size-4" />Filter</x-button>@if (request()->hasAny(['q', 'status', 'due']))<a href="{{ route('admin.jobs.index') }}" class="text-link">Clear</a>@endif</div>
        </form>
    </x-card>
    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        @forelse ($jobs as $job)
            <x-job-card :job="$job" role="admin" />
        @empty
            <x-empty-state icon="briefcase" title="No jobs found" class="xl:col-span-2">Try a different filter or create a job.<x-slot:actions><x-button href="{{ route('admin.jobs.create') }}">Create job</x-button></x-slot:actions></x-empty-state>
        @endforelse
    </div>
    <div class="mt-7">{{ $jobs->links() }}</div>
</x-app-layout>
