<x-guest-layout title="Sign in">
    <div class="mx-auto grid max-w-5xl items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)] lg:gap-12">
        <div class="mx-auto w-full max-w-md lg:mx-0">
            <p class="page-eyebrow">Welcome back</p>
            <h1 class="page-title">Sign in to your workspace</h1>
            <p class="page-lede">Your jobs, documents and team in one place.</p>
            <x-card class="mt-8">
                <x-feedback />
                <form method="POST" action="{{ route('login.store') }}" class="space-y-5" aria-label="Sign in">
                    @csrf
                    <x-input name="email" label="Email address" type="email" autocomplete="username" :value="old('email')" maxlength="255" required autofocus />
                    <x-input name="password" label="Password" type="password" autocomplete="current-password" required />
                    <x-button type="submit" class="w-full">Sign in <x-icon name="arrow" class="size-4" /></x-button>
                    <a href="{{ route('password.request') }}" class="text-link">Forgot your password?</a>
                </form>
            </x-card>
            <a href="{{ route('home') }}" class="text-link mt-5"><span aria-hidden="true">←</span> Back to NestCrafters</a>
        </div>
        @if (config('identity.registration_enabled'))
            <aside class="dash-hero block" aria-labelledby="new-contractor">
                <div class="relative z-10">
                    <p class="dash-eyebrow">New to NestCrafters?</p>
                    <h2 id="new-contractor" class="mt-3 text-2xl font-black tracking-tight">Join as a contractor</h2>
                    <ol class="mt-5 space-y-4 text-sm leading-6 text-white/85">
                        @foreach (['Register your company and services.', 'Verify your email address.', 'An administrator reviews and approves your account.', 'Accept assigned jobs and document your work.'] as $step)
                            <li class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full border-2 border-ink bg-signal-yellow text-xs font-black text-ink">{{ $loop->iteration }}</span><span class="pt-0.5">{{ $step }}</span></li>
                        @endforeach
                    </ol>
                    <a href="{{ route('register') }}" class="dash-action dash-action-light mt-6">Register as a contractor <x-icon name="arrow" class="size-4" /></a>
                </div>
            </aside>
        @endif
    </div>
</x-guest-layout>
