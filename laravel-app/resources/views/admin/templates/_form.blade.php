@php($isEdit = $template !== null)
<form method="POST" action="{{ $isEdit ? route('admin.templates.update', $template) : route('admin.templates.store') }}" class="space-y-6">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <x-input name="name" label="Template name" value="{{ old('name', $template?->name) }}" maxlength="100" required />
    <div>
        <label for="type" class="mb-2 block text-sm font-medium text-stone-700">Type</label>
        <select id="type" name="type" required class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base">
            @foreach(['Jobs', 'Email', 'Contractor'] as $type)
                <option value="{{ $type }}" @selected(old('type', $template?->type ?: 'Jobs') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="html_content" class="mb-2 block text-sm font-medium text-stone-700">Rich HTML content</label>
        <div class="mb-2 flex flex-wrap gap-2" aria-label="Formatting toolbar">
            <button type="button" data-format="strong" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm font-semibold" aria-label="Bold selected text">Bold</button>
            <button type="button" data-format="em" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm italic" aria-label="Italicize selected text">Italic</button>
            <button type="button" data-format="h3" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm font-semibold" aria-label="Make selected text a heading">Heading</button>
            <button type="button" data-format="p" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm" aria-label="Wrap selected text in a paragraph">Paragraph</button>
            <button type="button" data-format="ul" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm" aria-label="Make selected lines a list">List</button>
        </div>
        <textarea id="html_content" name="html_content" rows="16" maxlength="200000" required class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 font-mono text-base leading-6">{{ old('html_content', $template?->html_content) }}</textarea>
        <p class="mt-2 text-sm text-stone-600">Use the toolbar for common formatting. Content is sanitized before preview and job insertion; the original HTML is retained.</p>
        @error('html_content')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="flex flex-wrap gap-3"><x-button type="submit">{{ $isEdit ? 'Save template' : 'Create template' }}</x-button><x-button href="{{ route('admin.templates.index') }}" variant="secondary">Cancel</x-button></div>
</form>
@once
<script>
document.querySelectorAll('[data-format]').forEach((button) => button.addEventListener('click', () => {
    const editor = document.getElementById('html_content');
    const start = editor.selectionStart, end = editor.selectionEnd, value = editor.value;
    const selected = value.slice(start, end) || 'selected text';
    const tag = button.dataset.format;
    const replacement = tag === 'ul' ? `<ul><li>${selected}</li></ul>` : `<${tag}>${selected}</${tag}>`;
    editor.setRangeText(replacement, start, end, 'select');
    editor.focus();
}));
</script>
@endonce
