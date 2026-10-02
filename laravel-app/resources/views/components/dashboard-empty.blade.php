@props(['icon' => 'check', 'title'])
<div class="dash-empty">
    <span class="inline-flex size-11 items-center justify-center rounded-full border border-current/20 bg-white"><x-icon :name="$icon" /></span>
    <p class="mt-3 font-semibold">{{ $title }}</p>
    @if(trim((string) $slot))<p class="mt-2 max-w-sm text-sm leading-6 text-stone-600">{{ $slot }}</p>@endif
</div>
