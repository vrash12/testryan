@props(['title' => 'Welcome', 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><x-head :title="$title" /></head>
<body class="portal flex min-h-dvh flex-col">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:min-h-11 focus:rounded-md focus:border-2 focus:border-ink focus:bg-white focus:p-3 focus:font-bold">Skip to content</a>
    <header class="border-b-2 border-ink bg-paper">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-8">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center rounded-md" aria-label="NestCrafters home"><x-brand /></a>
            @guest
                <nav aria-label="Account" class="flex flex-wrap items-center gap-2 sm:gap-3">
                    @unless (request()->routeIs('login'))<a href="{{ route('login') }}" class="text-link px-2">Sign in</a>@endunless
                    @if (config('identity.registration_enabled') && ! request()->routeIs('register'))<x-button :href="route('register')" class="px-3">Join as contractor</x-button>@endif
                </nav>
            @endguest
        </div>
    </header>
    <main id="main-content" @class(['mx-auto w-full flex-1 px-4 sm:px-8', 'max-w-6xl py-8 sm:py-12' => $wide, 'max-w-6xl py-10 sm:py-16' => ! $wide])>{{ $slot }}</main>
    <footer class="border-t-2 border-ink bg-paper">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-stone-600 sm:px-8">
            <p><span class="font-black text-brand-700">NestCrafters</span> · Contractor management portal</p>
            <a href="{{ route('health') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold hover:text-brand-700"><x-icon name="check" class="size-4" />System health</a>
        </div>
    </footer>
</body>
</html>
