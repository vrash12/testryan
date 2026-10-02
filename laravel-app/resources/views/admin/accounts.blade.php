<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Account access">
    <h1 class="page-title">Account access</h1>
    <p class="mt-3 max-w-xl leading-7 text-stone-600">Review account access and set a password when needed. Password changes end the account’s existing sessions.</p>
    <div class="mt-7 space-y-4">
        @foreach ($accounts as $account)
            <x-card>
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <h2 class="break-words text-lg font-semibold">{{ $account->first_name }} {{ $account->last_name }}</h2>
                        <p class="mt-1 break-words text-sm text-stone-600">{{ $account->company_name }}</p>
                        <p class="mt-2 break-all text-sm text-stone-600">{{ $account->email ?: 'Managed contact · no sign-in email' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2"><x-badge>{{ ucfirst($account->role() ?? 'Unknown role') }}</x-badge><x-badge :tone="$account->hasVerifiedEmail() ? 'success' : 'neutral'">{{ $account->hasVerifiedEmail() ? 'Email verified' : 'Email unverified' }}</x-badge></div>
                </div>
                @if ($account->role() === 'contractor')<p class="mt-4 text-sm">Approval: {{ match ($account->is_approved) { 1 => 'Approved', 2 => 'Pending admin review', default => 'Not approved' } }}</p>@endif
                @if ($account->accountState?->blocked_at || $account->accountState?->archived_at)<p class="mt-3 text-sm text-red-700">Account access is disabled.</p>@endif
                @foreach ($account->accountState?->registration_signals ?? [] as $signal)<p class="mt-2 text-sm text-amber-800">Review signal: {{ str_replace('_', ' ', $signal) }}</p>@endforeach
                @can('resetPassword', $account)<x-button :href="route('admin.accounts.password', $account)" variant="secondary" class="mt-4">Set password</x-button>@endcan
            </x-card>
        @endforeach
    </div>
    <div class="mt-7">{{ $accounts->links() }}</div>
</x-app-layout>
