@props(['name', 'label', 'type' => 'text', 'hint' => null])
<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-bold text-ink">{{ $label }}</label>
    <div @class(['relative' => $type === 'password'])>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($hint || $errors->has($name)) aria-describedby="{{ $name }}-help" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->class(['nb-control min-h-12 w-full px-3 py-2 disabled:bg-stone-100 disabled:text-stone-500' => $type !== 'file', 'pr-20' => $type === 'password']) }}>
    @if ($type === 'password')
        <button hidden type="button" data-password-toggle aria-controls="{{ $name }}" aria-label="Show {{ strtolower($label) }}" aria-pressed="false" class="absolute right-1 top-0.5 min-h-11 min-w-16 rounded px-2 text-sm font-bold text-brand-700 underline underline-offset-4">Show</button>
    @endif
    </div>
    @if ($errors->has($name) || $hint)
        <p id="{{ $name }}-help" class="mt-2 text-sm {{ $errors->has($name) ? 'font-semibold text-red-700' : 'text-stone-600' }}">{{ $errors->first($name) ?: $hint }}</p>
    @endif
</div>
