<x-app-layout :role="$user->role()" :navigation="config('nestcrafters.navigation.'.$user->role())" title="Account settings">
    <x-page-header eyebrow="Your workspace, your way" title="Account settings">Your profile, email preferences and sign-in details, together in one place.</x-page-header>

    <div class="mt-7 grid items-start gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.4fr)]">
        <section aria-label="Your account" class="overflow-hidden rounded-lg border-2 border-ink bg-brand-700 text-white shadow-[4px_4px_0_#141a1d]">
            <div class="border-b border-white/20 p-6 sm:p-8">
                <div class="mb-6 flex size-16 items-center justify-center rounded-lg border-2 border-ink bg-signal-yellow text-2xl font-black text-ink">{{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}</div>
                <p class="text-xs font-bold uppercase tracking-widest text-signal-yellow">{{ ucfirst($user->role()) }} account</p>
                <h2 class="mt-2 break-words text-2xl font-black">{{ $user->first_name }} {{ $user->last_name }}</h2>
                @if($user->company_name)<p class="mt-2 break-words text-sm leading-6 text-white/75">{{ $user->company_name }}</p>@endif
                <p class="mt-5 break-all text-sm text-white/90">{{ $user->email }}</p>
            </div>
            <div class="space-y-4 p-6 sm:px-8">
                <div class="flex items-center gap-3 text-sm"><x-icon name="check" class="text-signal-yellow" /><span>Email verified</span></div>
                <div class="flex items-center gap-3 text-sm"><x-icon name="status" class="text-signal-yellow" /><span>{{ $user->role() === 'admin' ? 'Administrator access' : 'Approved for work' }}</span></div>
            </div>
        </section>

        <div class="space-y-5">
            @if($user->role() === 'contractor')
                <x-card class="!bg-[#e8f0f1]">
                    <div class="flex gap-4"><span class="flex size-11 shrink-0 items-center justify-center rounded-md border-2 border-ink bg-white"><x-icon name="profile" /></span><div class="min-w-0"><h2 class="text-lg font-black text-brand-700">Profile & services</h2><p class="mt-2 text-sm leading-6 text-stone-700">Update your company details, contact information, photo and the services you offer.</p><p class="mt-2 text-xs font-semibold text-brand-700">Saving profile or service changes returns your account to admin review.</p><x-button class="mt-5" :href="route('contractor.profile.edit', $user)" variant="secondary">Edit profile <x-icon name="arrow" class="size-4" /></x-button></div></div>
                </x-card>
            @endif
            <x-card class="!bg-[#fff2cf]">
                <div class="flex gap-4"><span class="flex size-11 shrink-0 items-center justify-center rounded-md border-2 border-ink bg-white"><x-icon name="bell" /></span><div class="min-w-0"><h2 class="text-lg font-black text-brand-700">Email preferences</h2><p class="mt-2 text-sm leading-6 text-stone-700">Choose which work updates reach your inbox. Security emails remain available.</p><x-button class="mt-5" :href="route('notifications.preferences')" variant="secondary">Manage email updates <x-icon name="arrow" class="size-4" /></x-button></div></div>
            </x-card>
            <x-card>
                <div class="flex gap-4"><span class="flex size-11 shrink-0 items-center justify-center rounded-md border-2 border-ink bg-mist"><x-icon name="lock" /></span><div class="min-w-0"><h2 class="text-lg font-black text-brand-700">Sign-in & security</h2><p class="mt-2 text-sm leading-6 text-stone-700">Need a new password? Sign out, then select “Forgot your password?” on the sign-in page to request a reset link.</p><form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<x-button type="submit" variant="secondary"><x-icon name="logout" class="size-4" />Sign out</x-button></form></div></div>
            </x-card>
        </div>
    </div>
</x-app-layout>
