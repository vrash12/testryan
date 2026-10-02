<x-guest-layout title="System health">
    <div class="mx-auto max-w-2xl">
        <p class="page-eyebrow">System health</p>
        <h1 class="page-title">{{ $healthy ? 'Everything is ready.' : 'Something needs attention.' }}</h1>
        <p class="mt-3 leading-7 text-stone-600">{{ app()->environment('local') ? 'A quick check of the local application foundation.' : 'The application is responding.' }}</p>
        <x-card class="mt-8 divide-y divide-stone-100">
            @foreach ($checks as $label => $ready)
                <div class="flex items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"><span class="text-sm font-medium">{{ $label }}</span><x-badge :tone="$ready ? 'success' : 'error'">{{ $ready ? 'Ready' : 'Unavailable' }}</x-badge></div>
            @endforeach
        </x-card>
        <x-button :href="route('health')" variant="secondary" class="mt-5">Check again</x-button>
    </div>
</x-guest-layout>
