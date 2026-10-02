@props(['tone' => 'info'])
@php
    $tones = [
        'info' => 'border-ink bg-brand-50 text-brand-700',
        'success' => 'border-ink bg-emerald-50 text-emerald-950',
        'warning' => 'border-ink bg-[#fff2cf] text-yellow-950',
        'error' => 'border-[#9f1d16] bg-[#fff1ee] text-red-950',
    ];
    $icon = ['info' => 'status', 'success' => 'check', 'warning' => 'alert', 'error' => 'alert'][$tone] ?? 'status';
@endphp
<div {{ $attributes->class(['flex gap-3 rounded-lg border-2 p-4 text-sm leading-6', $tones[$tone] ?? $tones['info']]) }} role="{{ $tone === 'error' ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="mt-0.5" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
