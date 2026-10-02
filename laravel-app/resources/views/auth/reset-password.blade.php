<x-guest-layout title="Choose a new password">
    <div class="mx-auto max-w-md">
        <p class="page-eyebrow">Password help</p>
        <h1 class="page-title">Choose a new password</h1>
        <p class="page-lede">Your other sessions will end when the password is updated.</p>
        <x-card class="mt-8"><x-feedback />
            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">@csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-input name="email" label="Email address" type="email" autocomplete="username" :value="old('email', $email)" required />
                <x-input name="password" label="New password" type="password" autocomplete="new-password" hint="Use a passphrase of at least 12 characters." required />
                <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
                <x-button type="submit" class="w-full">Update password</x-button>
            </form>
        </x-card>
        <a href="{{ route('password.request') }}" class="text-link mt-5">Request a new reset link</a>
    </div>
</x-guest-layout>
