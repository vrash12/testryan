@props(['items', 'dashboardUrl', 'preview' => false])
@foreach ($items as $item)
    @php $icon = match ($item) { 'Dashboard' => 'grid', 'Review queue' => 'review', 'Contractors' => 'people', 'Jobs', 'My jobs' => 'job', 'Services' => 'services', 'Documents' => 'file', 'Templates' => 'templates', 'Activity' => 'activity', 'Notifications' => 'bell', 'Account access' => 'lock', 'Account status' => 'status', 'Profile & services' => 'profile', default => 'grid' }; @endphp
    @if ($loop->first || (! $preview && auth()->check()))
        @php
            $url = match ($item) {
                'Account access' => route('admin.accounts.index'),
                'Account status' => route('account.status'),
                'Contractors' => route('admin.contractors.index'),
                'Jobs' => route('admin.jobs.index'),
                'Services' => route('admin.services.index'),
                'Review queue' => route('admin.review.index'),
                'Templates' => route('admin.templates.index'),
                'Activity' => route('admin.activity.index'),
                'Notifications' => route('notifications.index'),
                'My jobs' => route('contractor.jobs.index'),
                'Documents' => auth()->user()->role() === 'contractor' ? route('contractor.documents.index', auth()->user()) : route('admin.documents.index'),
                'Profile & services' => route('contractor.profile.edit', auth()->user()),
                default => $dashboardUrl,
            };
            $active = match ($item) {
                'Account access' => request()->routeIs('admin.accounts.*'),
                'Account status' => request()->routeIs('account.status', 'verification.notice'),
                'Contractors' => request()->routeIs('admin.contractors.*'),
                'Jobs' => request()->routeIs('admin.jobs.*'),
                'Services' => request()->routeIs('admin.services.*'),
                'Review queue' => request()->routeIs('admin.review.*'),
                'Templates' => request()->routeIs('admin.templates.*'),
                'Activity' => request()->routeIs('admin.activity.*'),
                'Notifications' => request()->routeIs('notifications.*', 'admin.notification-deliveries.*'),
                'My jobs' => request()->routeIs('contractor.jobs.*'),
                'Documents' => request()->routeIs('contractor.documents.*', 'admin.contractors.documents.*', 'admin.documents.*'),
                'Profile & services' => request()->routeIs('contractor.profile.*'),
                default => request()->routeIs('admin.dashboard', 'contractor.dashboard', 'local.preview'),
            };
        @endphp
        <a href="{{ $url }}" aria-label="{{ $item }}" title="{{ $item }}" @if($active) aria-current="page" @endif class="workspace-link flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm {{ $active ? 'bg-brand-50 font-semibold text-brand-700' : 'text-stone-600 hover:bg-stone-50' }}"><x-icon :name="$icon" /><span class="navigation-label">{{ $item }}</span></a>
    @else
        <button type="button" disabled aria-label="{{ $item }} (coming soon)" title="{{ $item }} (coming soon)" class="workspace-link flex min-h-12 w-full items-center gap-3 rounded-xl px-3 text-left text-sm text-stone-500">
            <x-icon :name="$icon" /><span class="navigation-label">{{ $item }}</span><span class="navigation-label ml-auto text-[11px]">Soon</span>
        </button>
    @endif
@endforeach
