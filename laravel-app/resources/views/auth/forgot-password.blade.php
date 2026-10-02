<x-guest-layout title="Password help">
    <div class="mx-auto max-w-md">
        <p class="page-eyebrow">Password help</p>
        <h1 class="page-title">Reset your password</h1>
        <p class="page-lede">Enter your account email. We’ll send a link if the account is eligible for recovery.</p>
        <x-card class="mt-8"><x-feedback />
            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">@csrf
                <x-input name="email" label="Email address" type="email" autocomplete="email" :value="old('email')" required />
                <x-button type="submit" class="w-full">Request reset link</x-button>
            </form>
        </x-card>
        <a href="{{ route('login') }}" class="text-link mt-5">Back to sign in</a>
    </div>
</x-guest-layout>
