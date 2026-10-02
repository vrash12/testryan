<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Contractors">
    <x-page-header eyebrow="People / access" title="Contractors">
        Review access, services, documents and account status in one place.
        <x-slot:actions><x-button href="{{ route('admin.review.index') }}" variant="secondary"><x-icon name="review" class="size-4" />Review queue</x-button><x-button href="{{ route('admin.contractors.print', request()->query()) }}" variant="secondary"><x-icon name="print" class="size-4" />Print directory</x-button><x-button href="{{ route('admin.contractors.create') }}"><x-icon name="plus" class="size-4" />Add contractor</x-button></x-slot:actions>
    </x-page-header>
    @php $advanced = collect(['priority', 'archived', 'missing_w9', 'blocked'])->filter(fn ($key) => $filters[$key] ?? false)->count() + count($filters['service_ids'] ?? []); @endphp
    <x-card class="mt-7">
        <form method="GET" role="search">
            <div class="filter-bar lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                <x-input name="q" type="search" label="Search" :value="$filters['q'] ?? ''" placeholder="Name, company, email or phone" />
                <div><label for="approval" class="mb-2 block text-sm font-bold">Approval</label><select id="approval" name="approval" class="w-full"><option value="">All states</option><option value="0" @selected(isset($filters['approval']) && (int) $filters['approval'] === 0)>Not approved</option><option value="1" @selected(isset($filters['approval']) && (int) $filters['approval'] === 1)>Approved</option><option value="2" @selected(isset($filters['approval']) && (int) $filters['approval'] === 2)>Pending review</option></select></div>
                <div class="filter-actions"><x-button type="submit" variant="dark"><x-icon name="search" class="size-4" />Filter</x-button><a href="{{ route('admin.contractors.index') }}" class="text-link">Clear filters</a></div>
            </div>
            <details class="group mt-5 rounded-md border-2 border-ink bg-paper" @if($advanced) open @endif>
                <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-3 px-4 text-sm font-black text-brand-700"><span>More filters @if($advanced)<x-badge tone="warning" class="ml-2">{{ $advanced }} active</x-badge>@endif</span><span class="transition-transform group-open:rotate-90" aria-hidden="true">›</span></summary>
                <div class="grid gap-x-6 gap-y-1 border-t-2 border-ink p-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (['priority' => 'Priority only', 'missing_w9' => 'Missing active W9', 'archived' => 'Include archived accounts', 'blocked' => 'Include blocked accounts'] as $key => $label)
                        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" name="{{ $key }}" value="1" @checked($filters[$key] ?? false) class="size-5"> {{ $label }}</label>
                    @endforeach
                    <fieldset class="mt-3 border-t border-stone-300 pt-3 sm:col-span-2 lg:col-span-4"><legend class="mb-2 pt-3 text-xs font-black uppercase tracking-wide text-brand-700">Must offer all selected services</legend><div class="flex flex-wrap gap-2">@foreach ($services as $service)<label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border-2 border-stone-300 bg-white px-3 text-sm has-[:checked]:border-ink has-[:checked]:bg-brand-50 has-[:checked]:font-bold"><input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" @checked(in_array($service->service_id, $filters['service_ids'] ?? [])) class="size-5">{{ $service->service_name }}</label>@endforeach</div></fieldset>
                </div>
            </details>
        </form>
    </x-card>
    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        @forelse ($contractors as $contractor)
            @php $status = match ((int) $contractor->is_approved) { 1 => ['Approved', 'success'], 2 => ['Pending admin review', 'warning'], default => ['Not approved', 'neutral'] }; @endphp
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h2 class="break-words text-xl font-black text-brand-700"><a href="{{ route('admin.contractors.show', $contractor) }}" class="hover:underline">{{ $contractor->first_name }} {{ $contractor->last_name }}</a></h2><p class="mt-1 break-words text-sm font-semibold text-stone-600">{{ $contractor->company_name }}</p><p class="mt-2 break-all text-sm text-stone-700">{{ $contractor->email ?: 'No sign-in email' }}</p></div><div class="flex flex-wrap gap-2"><x-badge :tone="$status[1]">{{ $status[0] }}</x-badge>@if($contractor->priority)<x-badge tone="warning">Priority</x-badge>@endif</div></div>
                @if ($contractor->accountState?->archived_at)<p class="mt-4 text-sm text-red-700">Archived {{ $contractor->accountState->archived_at->format('M j, Y') }}.</p>@endif
                @if ($contractor->accountState?->blocked_at)<p class="mt-3 text-sm text-red-700">Account access blocked.</p>@endif
                <p class="mt-3 break-words text-sm text-stone-600">Services: {{ $contractor->services->pluck('service_name')->join(', ') ?: 'None selected' }}</p><p class="mt-2 text-sm text-stone-600">{{ $contractor->completed_jobs_count }} completed job(s)</p>
                <div class="mt-5 flex flex-wrap gap-3"><x-button href="{{ route('admin.contractors.show', $contractor) }}">Open account <x-icon name="arrow" class="size-4" /></x-button><x-button href="{{ route('admin.contractors.edit', $contractor) }}" variant="secondary">Edit</x-button></div>
            </x-card>
        @empty
            <x-empty-state icon="people" title="No contractors found" class="xl:col-span-2">Try a different search or create the first contractor account.</x-empty-state>
        @endforelse
    </div>
    <div class="mt-7">{{ $contractors->links() }}</div>
</x-app-layout>
