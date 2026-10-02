@props(['href' => null, 'variant' => 'primary', 'type' => 'button'])
@php
    $classes = 'nb-button nb-button-'.$variant.' inline-flex min-h-11 items-center justify-center gap-2 px-4 py-2.5 text-sm';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
