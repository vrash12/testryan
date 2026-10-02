@props(['job', 'role'])
@php
    $isAdmin = $role === 'admin';
    $statusValue = $job->status()->value;
    $status = match ($statusValue) { 'completed' => ['Completed', 'success'], 'accepted' => ['Accepted', 'success'], 'pending' => ['Pending acceptance', 'warning'], default => ['Unassigned', 'neutral'] };
    $days = $job->due_date ? (int) today()->diffInDays($job->due_date, false) : null;
    $open = $statusValue !== 'completed';
    $overdue = $open && $days !== null && $days < 0;
    $dueClass = $open && $days !== null ? ($days < 0 ? 'font-bold text-red-700' : ($days <= 7 ? 'font-bold text-amber-800' : 'text-stone-700')) : 'text-stone-600';
    $dueText = $job->due_date ? 'Due '.$job->due_date->format('M j, Y').($overdue ? ' · Overdue' : ($open && $days === 0 ? ' · Today' : '')) : 'No due date';
    $url = $isAdmin ? route('admin.jobs.show', $job) : route('contractor.jobs.show', $job);
    $needsAcceptance = ! $isAdmin && $statusValue === 'pending';
@endphp
<article @class(['nb-card flex flex-col p-5', 'card-flag-accept' => $needsAcceptance, 'card-flag-overdue' => ! $needsAcceptance && $overdue])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="min-w-0 flex-1 break-words text-lg font-black leading-snug text-brand-700"><a href="{{ $url }}" class="hover:underline">{{ $job->job_name }}</a></h2>
        <x-badge :tone="$status[1]">{{ $status[0] }}</x-badge>
    </div>
    <div class="record-meta mt-3">
        <span class="inline-flex items-center gap-1.5 {{ $dueClass }}"><x-icon name="calendar" class="size-4" />{{ $dueText }}</span>
        @if ($isAdmin)<span class="inline-flex min-w-0 items-center gap-1.5 break-words"><x-icon name="people" class="size-4" />{{ $job->contractor?->company_name ?: ($job->contractor ? $job->contractor->first_name.' '.$job->contractor->last_name : 'No contractor assigned') }}</span>@endif
        @if ($job->approved_price !== null)<span class="inline-flex items-center gap-1.5 tabular-nums"><x-icon name="money" class="size-4" /><span class="sr-only">Approved price</span>{{ number_format((float) $job->approved_price, 2) }}</span>@endif
    </div>
    @if ($job->services->isNotEmpty())<div class="mt-4 flex flex-wrap gap-2">@foreach ($job->services as $service)<x-badge>{{ $service->service_name }}</x-badge>@endforeach</div>@endif
    <div class="mt-auto flex flex-wrap gap-3 pt-5">
        @if ($isAdmin)
            <x-button :href="$url">Open job <x-icon name="arrow" class="size-4" /></x-button>
            <x-button href="{{ route('admin.jobs.edit', $job) }}" variant="secondary">Edit</x-button>
        @else
            <x-button :href="$url" :variant="$needsAcceptance ? 'primary' : 'secondary'">{{ $needsAcceptance ? 'Review and accept' : 'Open job' }} <x-icon name="arrow" class="size-4" /></x-button>
            <x-button href="{{ route('contractor.jobs.print', $job) }}" variant="secondary"><x-icon name="print" class="size-4" />Print view</x-button>
        @endif
    </div>
</article>
