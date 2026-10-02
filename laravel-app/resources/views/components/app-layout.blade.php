@props(['role', 'navigation', 'preview' => false, 'title' => 'Dashboard'])
@php
    $dashboardUrl = $preview ? route('local.preview', $role) : route($role.'.dashboard');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><x-head :title="$title" /></head>
<body class="portal role-{{ $role }} min-h-dvh">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:min-h-11 focus:rounded-md focus:border-2 focus:border-ink focus:bg-white focus:p-3 focus:font-bold">Skip to content</a>
    <aside id="workspace-sidebar" class="workspace-sidebar fixed inset-y-0 left-0 z-30 hidden w-64 flex-col overflow-y-auto lg:flex">
        <button type="button" data-sidebar-toggle aria-controls="workspace-sidebar" aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation" class="sidebar-logo inline-flex min-h-11 shrink-0 items-center rounded-lg text-left"><x-brand /></button>
        <p class="sidebar-heading mb-4 mt-6 px-3 text-xs font-semibold uppercase tracking-widest text-signal-yellow">{{ $role === 'admin' ? 'Management' : 'Your workspace' }}</p>
        <nav aria-label="Primary navigation" class="sidebar-sections min-h-0 flex-1 space-y-1 overflow-y-auto py-2"><x-navigation :items="$navigation" :dashboard-url="$dashboardUrl" :preview="$preview" /></nav>
        @if (! $preview)
            <div class="sidebar-footer mt-4 shrink-0 border-t border-current/20 pt-3">
                <a href="{{ route('account.settings') }}" aria-label="Account settings" title="Account settings" @if(request()->routeIs('account.settings', 'account.status')) aria-current="page" @endif class="sidebar-account flex min-h-11 items-center gap-3 rounded-md aria-[current=page]:ring-2 aria-[current=page]:ring-brand-orange">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-md border-2 border-ink text-sm font-black">{{ mb_substr(auth()->user()->first_name ?? '', 0, 1) }}{{ mb_substr(auth()->user()->last_name ?? '', 0, 1) }}</span>
                    <span class="navigation-label min-w-0 flex-1 text-sm font-semibold"><span class="block truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span><span class="block text-xs opacity-70">Account settings</span></span>
                    <span class="navigation-label"><x-icon name="settings" class="size-4" /></span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button type="submit" aria-label="Sign out" title="Sign out" class="sidebar-logout flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-sm font-semibold"><x-icon name="logout" /><span class="navigation-label">Sign out</span></button></form>
            </div>
        @endif
    </aside>
    <div class="workspace-content lg:pl-64">
        <header class="sticky top-0 z-20 border-b-2 border-ink bg-paper/95 backdrop-blur supports-[backdrop-filter]:bg-paper/85">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-8">
                <button type="button" data-mobile-menu aria-controls="mobile-navigation" aria-expanded="false" aria-label="Toggle navigation" class="inline-flex min-h-11 min-w-0 shrink items-center overflow-hidden text-left lg:hidden"><x-brand /></button>
                <p class="hidden min-w-0 truncate text-sm font-semibold text-stone-600 lg:block">{{ $role === 'admin' ? 'Management' : 'Your workspace' }} <span class="mx-2 text-stone-400" aria-hidden="true">/</span><span class="font-black text-brand-700">{{ $title }}</span></p>
                <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                    @if (! $preview)
                        @php $unreadCount = config('notifications.inbox_enabled') ? \App\Models\InboxNotification::where('user_id', auth()->id())->whereNull('read_at')->count() : 0; @endphp
                        <a href="{{ route('notifications.index') }}" aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}" title="Notifications" @if(request()->routeIs('notifications.*')) aria-current="page" @endif class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border-2 border-ink bg-white text-brand-700 hover:bg-mist aria-[current=page]:bg-mist"><x-icon name="bell" />@if($unreadCount)<span class="absolute -right-2 -top-2 min-w-6 rounded-full border-2 border-ink bg-brand-orange px-1.5 text-center text-xs font-black text-ink">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</a>
                    @endif
                    <span class="hidden sm:block"><x-badge :tone="$role === 'admin' ? 'dark' : 'info'">{{ ucfirst($role) }}</x-badge></span>
                    @if (! $preview)
                        <form method="POST" action="{{ route('logout') }}" class="lg:hidden">@csrf<x-button variant="secondary" type="submit" class="min-w-11 px-2.5 sm:px-3" aria-label="Sign out"><x-icon name="logout" class="size-4" /><span class="hidden sm:inline">Sign out</span></x-button></form>
                    @endif
                </div>
            </div>
            <details id="mobile-navigation" class="border-t-2 border-ink bg-paper lg:hidden">
                <summary class="flex min-h-11 cursor-pointer items-center gap-2 px-4 text-sm font-bold text-brand-700"><x-icon name="menu" />{{ $role === 'admin' ? 'Management menu' : 'Workspace menu' }}</summary>
                <nav aria-label="Mobile navigation" class="space-y-1 px-4 pb-4"><x-navigation :items="$navigation" :dashboard-url="$dashboardUrl" :preview="$preview" />@unless($preview)<a href="{{ route('account.settings') }}" class="workspace-link flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold"><x-icon name="settings" />Account settings</a>@endunless</nav>
            </details>
        </header>
        <main id="main-content" class="mx-auto max-w-7xl px-4 pb-28 pt-6 sm:px-8 sm:pt-8 lg:pb-12">
            @if ($preview)
                <div class="mb-8 flex flex-wrap items-center justify-between gap-2 rounded-lg border-2 border-ink bg-[#fff2cf] px-4 py-3 text-sm text-yellow-950">
                    <p><span class="font-semibold">Local shell preview.</span> No account or job records are displayed in this preview.</p>
                    <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold underline underline-offset-4">Switch workspace <x-icon name="arrow" class="size-4" /></a>
                </div>
            @endif
            <x-feedback />
            {{ $slot }}
        </main>
        <nav aria-label="Phone shortcuts" class="fixed inset-x-0 bottom-0 z-30 grid {{ $preview ? 'grid-cols-2' : 'grid-cols-4' }} gap-1 border-t-2 border-ink bg-paper px-3 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] lg:hidden">
            <a href="{{ $dashboardUrl }}" @if(request()->routeIs('admin.dashboard', 'contractor.dashboard', 'local.preview')) aria-current="page" @endif class="phone-shortcut"><x-icon />Dashboard</a>
            @if (! $preview)
                @if ($role === 'admin')
                    <a href="{{ route('admin.contractors.index') }}" @if(request()->routeIs('admin.contractors.*', 'admin.review.*')) aria-current="page" @endif class="phone-shortcut"><x-icon name="people" />Contractors</a>
                    <a href="{{ route('admin.jobs.index') }}" @if(request()->routeIs('admin.jobs.*')) aria-current="page" @endif class="phone-shortcut"><x-icon name="job" />Jobs</a>
                @else
                    <a href="{{ route('contractor.jobs.index') }}" @if(request()->routeIs('contractor.jobs.*')) aria-current="page" @endif class="phone-shortcut"><x-icon name="job" />Jobs</a>
                    <a href="{{ route('contractor.documents.index', auth()->user()) }}" @if(request()->routeIs('contractor.documents.*')) aria-current="page" @endif class="phone-shortcut"><x-icon name="file" />Documents</a>
                @endif
            @endif
            @if ($role === 'contractor' && ! $preview)
                <a href="{{ route('account.settings') }}" @if(request()->routeIs('account.settings', 'account.status')) aria-current="page" @endif class="phone-shortcut"><x-icon name="settings" />Settings</a>
            @else
                <button type="button" data-mobile-menu aria-controls="mobile-navigation" aria-expanded="false" class="phone-shortcut"><x-icon name="menu" />More</button>
            @endif
        </nav>
    </div>
</body>
</html>
