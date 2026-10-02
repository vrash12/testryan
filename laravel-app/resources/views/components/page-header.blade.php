@props(['eyebrow' => null, 'title', 'back' => null, 'backLabel' => 'Back'])
<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0 max-w-3xl">
        @if ($back)<a href="{{ $back }}" class="text-link -mt-2 mb-1"><span aria-hidden="true">←</span> {{ $backLabel }}</a>@endif
        @if ($eyebrow)<p class="page-eyebrow">{{ $eyebrow }}</p>@endif
        <h1 class="page-title">{{ $title }}</h1>
        @if (trim((string) $slot) !== '')<p class="page-lede">{{ $slot }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap gap-3">{{ $actions }}</div>@endisset
</div>
