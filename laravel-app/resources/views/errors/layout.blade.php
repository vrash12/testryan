<x-guest-layout :title="trim($__env->yieldContent('title'))">
    <div class="mx-auto max-w-xl py-6 sm:py-12">
        <x-badge tone="warning">@yield('code')</x-badge>
        <h1 class="mt-5 text-3xl font-semibold tracking-tight">@yield('title')</h1>
        <p class="mt-4 leading-7 text-stone-500">@yield('message')</p>
        <x-button :href="route('home')" variant="secondary" class="mt-7">Back to NestCrafters</x-button>
    </div>
</x-guest-layout>
