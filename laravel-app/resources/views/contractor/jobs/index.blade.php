<x-app-layout role="contractor" :navigation="config('nestcrafters.navigation.contractor')" title="My jobs">
    <x-page-header eyebrow="Your workspace" title="My jobs">Review assigned work and terms, accept a pending job, then add photos or documents as work progresses.</x-page-header>
    <x-card class="mt-6"><form method="GET" class="filter-bar" role="search">
        <x-input name="q" type="search" label="Search jobs" :value="request('q')" placeholder="Job name" />
        <div><label for="status" class="mb-2 block text-sm font-bold">Status</label><select id="status" name="status" class="w-full"><option value="">All statuses</option>@foreach (['open' => 'Open', 'pending' => 'Pending acceptance', 'accepted' => 'Accepted', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="due" class="mb-2 block text-sm font-bold">Due date</label><select id="due" name="due" class="w-full"><option value="">All dates</option><option value="overdue" @selected(request('due') === 'overdue')>Overdue</option><option value="upcoming" @selected(request('due') === 'upcoming')>Next 7 days</option></select></div>
        <div class="filter-actions"><x-button type="submit" variant="dark"><x-icon name="search" class="size-4" />Filter</x-button>@if (request()->hasAny(['q', 'status', 'due']))<a href="{{ route('contractor.jobs.index') }}" class="text-link">Clear</a>@endif</div>
    </form></x-card>
    <div class="mt-7 grid gap-5 xl:grid-cols-2">
        @forelse ($jobs as $job)
            <x-job-card :job="$job" role="contractor" />
        @empty
            <x-empty-state icon="briefcase" title="No assigned jobs" class="xl:col-span-2">{{ request()->hasAny(['q', 'status', 'due']) ? 'No jobs match these filters. Clear them to see all of your work.' : 'New work will appear here when an administrator assigns it to you.' }}</x-empty-state>
        @endforelse
    </div>
    <div class="mt-7">{{ $jobs->links() }}</div>
</x-app-layout>
