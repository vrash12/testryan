@php
    $isContractor = $user->role() === 'contractor';
    $verified = $user->hasVerifiedEmail();
    $approved = $user->is_approved === 1;
    $canEnter = $verified && ($user->role() === 'admin' || $user->canAccessWork());
    $reviewCopy = match ($user->is_approved) {
        1 => 'Your contractor account is approved.',
        2 => 'Your changes are awaiting administrator review. Work access resumes after approval.',
        default => 'Your registration is awaiting administrator approval. You can verify your email while you wait.',
    };
    $steps = array_values(array_filter([
        ['title' => 'Account created', 'state' => 'done', 'copy' => 'Signed in as '.$user->email.'.'],
        ['title' => 'Email verification', 'state' => $verified ? 'done' : 'current', 'copy' => $verified ? 'Your email address is verified.' : 'Verify your email address before entering your workspace. Check for a verification message or request a new one.'],
        $isContractor ? ['title' => 'Administrator review', 'state' => $approved ? 'done' : ($verified ? 'current' : 'waiting'), 'copy' => $reviewCopy] : null,
        ['title' => 'Workspace access', 'state' => $canEnter ? 'done' : 'waiting', 'copy' => $canEnter ? 'Everything is in place. Your workspace is open.' : 'Opens once the steps above are complete.'],
    ]));
@endphp
<x-guest-layout title="Account status">
    <div class="mx-auto max-w-2xl">
        <p class="page-eyebrow">Your account</p>
        <h1 class="page-title">Welcome, {{ $user->first_name }}</h1>
        <p class="page-lede break-words">{{ $canEnter ? 'Your account is ready.' : 'Here is where your account stands and what happens next.' }}</p>

        <div class="mt-8"><x-feedback /></div>

        <x-card>
            <ol class="space-y-0" aria-label="Account progress">
                @foreach ($steps as $step)
                    @php $done = $step['state'] === 'done'; $current = $step['state'] === 'current'; @endphp
                    <li class="relative flex gap-4 pb-7 last:pb-0">
                        @unless ($loop->last)<span class="absolute left-[17px] top-10 bottom-1 w-0.5 {{ $done ? 'bg-ink' : 'bg-mist' }}" aria-hidden="true"></span>@endunless
                        <span @class(['relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-black', 'border-ink bg-signal-green text-white' => $done, 'border-ink bg-signal-yellow text-ink' => $current, 'border-stone-400 bg-white text-stone-600' => ! $done && ! $current]) aria-hidden="true">@if($done)<x-icon name="check" class="size-4" />@else{{ $loop->iteration }}@endif</span>
                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-black text-brand-700">{{ $step['title'] }}</h2>
                                <x-badge :tone="$done ? 'success' : ($current ? 'warning' : 'neutral')">{{ $done ? ($step['title'] === 'Email verification' ? 'Verified' : ($step['title'] === 'Administrator review' ? 'Approved' : 'Done')) : ($current ? ($step['title'] === 'Administrator review' ? 'In review' : 'Action needed') : 'Waiting') }}</x-badge>
                            </div>
                            <p class="mt-2 break-words text-sm leading-6 text-stone-700">{{ $step['copy'] }}</p>
                            @if ($step['title'] === 'Email verification' && ! $verified)
                                <form method="POST" action="{{ route('verification.send') }}" class="mt-4">@csrf<x-button type="submit"><x-icon name="mail" class="size-4" />Request verification email</x-button></form>
                            @endif
                            @if ($step['title'] === 'Administrator review' && ! $approved)
                                <p class="mt-3 text-xs text-stone-600">No action is needed from you. You can keep this page open or sign out and check back later.</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($canEnter)
                <x-button :href="route('dashboard')">Open workspace <x-icon name="arrow" class="size-4" /></x-button>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<x-button variant="secondary" type="submit">Sign out</x-button></form>
        </div>
    </div>
</x-guest-layout>
