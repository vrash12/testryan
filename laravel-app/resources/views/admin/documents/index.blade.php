<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Documents">
    <h1 class="page-title">Contractor documents</h1>
    <p class="mt-3 text-stone-600">Active contractor accounts. Insurance reminders include today through 30 days from today.</p>
    <x-card class="mt-6"><form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 sm:items-end">
        <x-input name="q" label="Search" :value="request('q')" placeholder="Filename, contractor or company" />
        <div><label for="status" class="mb-2 block">Status</label><select id="status" name="status" class="min-h-12 w-full rounded-xl border border-stone-300 px-3">@foreach(['active'=>'Active','archived'=>'Archived','expiring'=>'Insurance expiring','expired'=>'Insurance expired'] as $value=>$label)<option value="{{ $value }}" @selected(request('status', 'active') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="type" class="mb-2 block">Type</label><select id="type" name="type" class="min-h-12 w-full rounded-xl border border-stone-300 px-3"><option value="">All types</option>@foreach(['W9','Insurance','Other'] as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="flex flex-wrap gap-3"><x-button type="submit">Filter</x-button><x-button href="{{ route('admin.documents.index') }}" variant="secondary">Clear</x-button></div>
    </form></x-card>
    <p class="mt-5 text-sm text-stone-600">{{ $documents->total() }} matching documents</p>
    <div class="mt-4 grid gap-4 xl:grid-cols-2">@forelse($documents as $document)
        <x-card><h2 class="break-words text-lg font-semibold">{{ $document->file_name }}</h2><p class="mt-2 break-words text-stone-600">{{ $document->owner->company_name }} · {{ $document->file_type }}</p><p class="mt-2 text-sm">Uploaded {{ $document->upload_date?->format('M j, Y') }} · {{ $document->expiry_date ? 'Expires '.$document->expiry_date->format('M j, Y') : 'No expiry date' }}</p><div class="mt-4 flex flex-wrap gap-3"><x-button href="{{ route('admin.contractors.documents.index', $document->owner) }}" variant="secondary">Manage documents</x-button><x-button href="{{ route('documents.preview', $document) }}" variant="secondary">Preview</x-button><x-button href="{{ route('documents.download', $document) }}" variant="secondary">Download</x-button></div></x-card>
    @empty<x-empty-state icon="file" title="No matching documents">Try another filter.</x-empty-state>@endforelse</div>
    <div class="mt-7">{{ $documents->links() }}</div>
</x-app-layout>
