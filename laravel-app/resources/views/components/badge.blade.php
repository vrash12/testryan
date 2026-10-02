@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'border-ink bg-mist text-ink',
        'success' => 'border-ink bg-emerald-100 text-emerald-950',
        'warning' => 'border-ink bg-yellow-200 text-yellow-950',
        'error' => 'border-ink bg-red-100 text-red-950',
        'info' => 'border-ink bg-white text-brand-700',
        'dark' => 'border-ink bg-brand-700 text-paper',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-bold uppercase tracking-wide', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
