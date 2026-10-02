@props(['title', 'icon' => 'grid'])
<div {{ $attributes->class(['flex flex-col items-center rounded-lg border-2 border-dashed border-stone-400 bg-white px-4 py-10 text-center sm:py-14']) }}>
    <span class="mb-4 flex size-14 items-center justify-center rounded-full border-2 border-ink bg-mist text-brand-700"><x-icon :name="$icon" class="size-6" /></span>
    <h3 class="text-lg font-black text-brand-700">{{ $title }}</h3>
    @if (trim((string) $slot) !== '')<p class="mt-2 max-w-sm text-sm leading-6 text-stone-600">{{ $slot }}</p>@endif
    @isset($actions)<div class="mt-5 flex flex-wrap justify-center gap-3">{{ $actions }}</div>@endisset
</div>
