@props(['name', 'label' => 'File', 'multiple' => false])
<div data-upload-picker data-max-files="{{ $multiple ? 10 : 1 }}" class="min-w-0 sm:col-span-full">
    <x-input :name="$name" type="file" :label="$label" accept="image/jpeg,image/png,application/pdf" :multiple="$multiple" required hint="JPEG, PNG or PDF, up to 10 MB per file. HEIC photos must be exported as JPEG. Select up to {{ $multiple ? 10 : 1 }} file(s)." />
    <div data-file-list class="mt-3 space-y-3"></div>
    <p data-upload-status role="status" aria-live="polite" class="mt-3 break-words text-sm font-semibold"></p>
</div>
