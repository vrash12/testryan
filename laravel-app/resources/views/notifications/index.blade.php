<x-app-layout :role="auth()->user()->role()" :navigation="config('nestcrafters.navigation.'.auth()->user()->role())" title="Notifications">
    <div class="dashboard-design">
        <section class="dash-hero">
            <div><p class="dash-eyebrow">Your workspace / Updates</p><h1 class="mt-3 text-3xl font-black">Notifications</h1><p class="mt-3 text-sm leading-7 text-white/80">Keep up with your team, jobs and account updates.</p></div>
            <a class="dash-action dash-action-light" href="{{ route('notifications.preferences') }}">Email preferences <x-icon name="arrow" /></a>
        </section>
        <nav aria-label="Notification status" class="my-6 flex flex-wrap gap-3">
            @foreach(['all' => ['All updates', $total], 'unread' => ['Unread', $unread], 'read' => ['Read', $total - $unread]] as $status => [$label, $count])
                <x-button :href="route('notifications.index', array_merge($filters, ['status' => $status]))" :variant="$filters['status'] === $status ? 'dark' : 'secondary'" :aria-current="$filters['status'] === $status ? 'page' : null">{{ $label }} <span class="tabular-nums">{{ $count }}</span></x-button>
            @endforeach
            @if(auth()->user()->role() === 'admin')<x-button href="{{ route('admin.notification-deliveries.index') }}" variant="secondary">Email delivery status</x-button>@endif
        </nav>
        <x-card class="mb-6">
            <form method="GET" action="{{ route('notifications.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filter notifications">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <div class="min-w-0"><label for="notification-type" class="mb-2 block text-sm font-bold">Update type</label><select id="notification-type" name="type" class="w-full"><option value="">All update types</option>@foreach($labels as $type => $label)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $label }}</option>@endforeach</select></div>
                <x-input name="from" type="date" label="From date" :value="$filters['from'] ?? ''" />
                <x-input name="to" type="date" label="Through date" :value="$filters['to'] ?? ''" />
                <div class="flex flex-wrap items-end gap-3"><x-button type="submit">Apply filters</x-button><x-button href="{{ route('notifications.index') }}" variant="secondary">Clear</x-button></div>
            </form>
            <p class="mt-3 text-xs text-stone-600">Dates include the whole day in {{ $timezone }}. Status counts cover your entire inbox.</p>
        </x-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black">Your updates</h2><p class="text-sm text-stone-600">{{ $items->total() }} matching {{ Str::plural('update', $items->total()) }}</p></div>
        @if($items->isNotEmpty())
            <form id="notification-bulk" method="POST" action="{{ route('notifications.bulk') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-md border-2 border-ink bg-mist p-4" aria-label="Update selected notifications">
                @csrf
                @foreach($filters as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                <div><label for="notification-action" class="mb-2 block text-sm font-bold">For selected updates</label><select name="action" id="notification-action"><option value="read">Mark read</option><option value="unread">Mark unread</option></select></div>
                <x-button type="submit">Apply to selected</x-button>
                <x-button type="button" variant="secondary" hidden data-inbox-select-toggle>Select this page</x-button>
                <p class="w-full text-sm">Only checked updates change. Choose up to 50; selections do not carry across pages.</p>
                <p hidden data-inbox-selection-count role="status" aria-live="polite" class="w-full text-sm font-bold"></p>
            </form>
        @endif
        <div id="ids" tabindex="-1" class="space-y-4">
            @forelse($items as $item)
                @php $url = $inbox->url($item->event, auth()->user()); $label = $labels[$item->event->type] ?? 'Workspace update'; @endphp
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <label class="mb-3 inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm font-semibold"><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="notification-bulk" data-inbox-item class="size-5" aria-label="Select {{ $label }} from {{ $item->created_at->format('M j, Y g:i A') }}">Select update</label>
                            <div class="flex flex-wrap items-center gap-3"><h3 class="text-lg font-bold">{{ $label }}</h3><x-badge :tone="$item->read_at ? 'neutral' : 'warning'">{{ $item->read_at ? 'Read' : 'Unread' }}</x-badge></div>
                            <p class="mt-2 text-sm text-stone-600"><time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->format('M j, Y g:i A') }}</time></p>
                            @if($item->event->type === 'document.expiring')<p class="mt-2 text-sm">Insurance expiry: {{ $item->event->payload['expiry_date'] }}.</p>@endif
                            <p class="mt-3 text-sm text-stone-600">{{ $url ? 'Open the record to see its current details.' : 'This record is no longer available to your account.' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @if($url)<x-button :href="$url" variant="secondary">View update</x-button>@endif
                            <form method="POST" action="{{ route('notifications.bulk') }}">
                                @csrf
                                <input type="hidden" name="ids[]" value="{{ $item->id }}"><input type="hidden" name="action" value="{{ $item->read_at ? 'unread' : 'read' }}">
                                @foreach($filters as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                <x-button type="submit" variant="secondary">{{ $item->read_at ? 'Mark unread' : 'Mark read' }}</x-button>
                            </form>
                        </div>
                    </div>
                </x-card>
            @empty
                <x-dashboard-empty icon="bell" :title="$total ? 'No updates match these filters' : 'No updates yet'">{{ $total ? 'Try another status, update type or date range. Clear filters to see your inbox.' : 'New updates will appear here as work happens. Earlier activity is not added retroactively.' }}</x-dashboard-empty>
            @endforelse
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    </div>
</x-app-layout>
