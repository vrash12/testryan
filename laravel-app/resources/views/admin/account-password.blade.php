<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Set account password">
    <div class="max-w-xl">
        <a href="{{ route('admin.accounts.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700">Back to account access</a>
        <h1 class="page-title">Set account password</h1>
        <p class="mt-3 break-words leading-7 text-stone-600">{{ $account->first_name }} {{ $account->last_name }} · {{ $account->company_name }}</p>
        <x-card class="mt-7">
            <x-alert>Existing sessions and recovery links for this account will end. Approval and email verification stay as they are.</x-alert>
            <form method="POST" action="{{ route('admin.accounts.password.update', $account) }}" class="mt-6 space-y-5">@csrf
                <x-input name="current_password" label="Your current administrator password" type="password" autocomplete="current-password" required />
                <x-input name="password" label="New account password" type="password" autocomplete="new-password" hint="Use a passphrase of at least 12 characters." required />
                <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                <x-button type="submit" class="w-full">Update password and end sessions</x-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
