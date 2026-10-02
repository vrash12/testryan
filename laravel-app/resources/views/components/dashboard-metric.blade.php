@props(['label', 'value', 'href', 'icon' => 'grid', 'hint' => '', 'tone' => 'plain'])
<a href="{{ $href }}" class="dash-metric dash-metric-{{ $tone }}">
    <span class="flex items-start justify-between gap-3"><span class="text-sm font-semibold">{{ $label }}</span><x-icon :name="$icon" class="opacity-70" /></span>
    <span class="mt-5 block text-4xl font-black tracking-tight tabular-nums">{{ $value }}</span>
    <span class="mt-3 flex items-center justify-between gap-2 text-xs font-medium"><span>{{ $hint }}</span><x-icon name="arrow" class="size-4" /></span>
</a>
