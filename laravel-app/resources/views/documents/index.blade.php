@php
    $active = $owner->documents->filter(fn ($document) => ! $document->archived);
    $w9 = $active->firstWhere('file_type', 'W9');
    $insurance = $active->where('file_type', 'Insurance')->sortByDesc(fn ($document) => $document->expiry_date?->timestamp ?? PHP_INT_MAX)->first();
    $insuranceState = match (true) {
        $insurance === null => ['Missing', 'error', 'Upload a current insurance certificate with its expiry date.'],
        $insurance->expiry_date === null => ['On file', 'success', 'No expiry date recorded.'],
        $insurance->expiry_date->lt(today()) => ['Expired', 'error', 'Expired '.$insurance->expiry_date->format('M j, Y').'. Upload a renewed certificate.'],
        $insurance->expiry_date->lte(today()->addDays(30)) => ['Expiring soon', 'warning', 'Expires '.$insurance->expiry_date->format('M j, Y').'. Renew within 30 days.'],
        default => ['Current', 'success', 'Valid until '.$insurance->expiry_date->format('M j, Y').'.'],
    };
@endphp
<x-app-layout :role="$adminView ? 'admin' : 'contractor'" :navigation="config('nestcrafters.navigation.'.($adminView ? 'admin' : 'contractor'))" title="Documents">
    <x-page-header eyebrow="Private documents" :title="$adminView ? $owner->first_name.' '.$owner->last_name : 'Your documents'" :back="$adminView ? route('admin.contractors.show', $owner) : null" back-label="Back to account">
        W9 and insurance files are private and only open for signed-in, authorized users. Expiry dates drive the admin's 30-day insurance watch.
    </x-page-header>

    <section aria-label="Document readiness" class="document-readiness mt-7 grid gap-4 sm:grid-cols-2">
        <div @class(['document-readiness-card nb-card flex items-start gap-4 p-5', 'is-ready' => $w9, 'is-missing' => ! $w9])>
            <span @class(['document-status-icon flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-ink', 'bg-signal-green text-white' => $w9, 'bg-red-100 text-red-900' => ! $w9]) aria-hidden="true"><x-icon :name="$w9 ? 'check' : 'alert'" /></span>
            <div class="min-w-0"><p class="document-card-kicker">Required document</p><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-black text-brand-700">W9</h2><x-badge :tone="$w9 ? 'success' : 'error'">{{ $w9 ? 'On file' : 'Missing' }}</x-badge></div><p class="mt-1 break-words text-sm text-stone-700">{{ $w9 ? ($w9->file_name ?: 'W9').' · uploaded '.$w9->upload_date?->format('M j, Y') : 'Upload a signed W9 form.' }}</p></div>
        </div>
        <div @class(['document-readiness-card nb-card flex items-start gap-4 p-5', 'is-ready' => $insuranceState[1] === 'success', 'is-warning' => $insuranceState[1] === 'warning', 'is-missing' => $insuranceState[1] === 'error'])>
            <span @class(['document-status-icon flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-ink', 'bg-signal-green text-white' => $insuranceState[1] === 'success', 'bg-signal-yellow text-ink' => $insuranceState[1] === 'warning', 'bg-red-100 text-red-900' => $insuranceState[1] === 'error']) aria-hidden="true"><x-icon :name="$insuranceState[1] === 'success' ? 'check' : 'alert'" /></span>
            <div class="min-w-0"><p class="document-card-kicker">Keep current</p><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-black text-brand-700">Insurance</h2><x-badge :tone="$insuranceState[1]">{{ $insuranceState[0] }}</x-badge></div><p class="mt-1 break-words text-sm text-stone-700">{{ $insuranceState[2] }}</p></div>
        </div>
    </section>

    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <x-card class="document-upload-card xl:sticky xl:top-6">
            <div class="flex items-start justify-between gap-4"><div><p class="document-card-kicker">Add to your records</p><h2 class="flex items-center gap-2 text-lg font-black text-brand-700"><x-icon name="upload" />Upload document</h2></div><span class="document-step" aria-hidden="true">01</span></div>
            <p class="mt-2 text-sm leading-6 text-stone-600">Choose the document type first, then add one private file. Insurance expiry dates keep your renewal reminders accurate.</p>
            <div class="mt-4"><x-feedback /></div>
            <form method="POST" action="{{ $adminView ? route('admin.contractors.documents.store', $owner) : route('contractor.documents.store', $owner) }}" enctype="multipart/form-data" class="mt-2 space-y-5">@csrf
                <div><label for="file_type" class="mb-2 block text-sm font-bold">Document type</label><select id="file_type" name="file_type" class="w-full"><option value="W9" @selected(old('file_type') === 'W9')>W9</option><option value="Insurance" @selected(old('file_type') === 'Insurance')>Insurance</option><option value="Other" @selected(old('file_type') === 'Other')>Other</option></select></div>
                <x-upload-picker name="document" label="File" />
                <x-input name="file_name" label="Display name (optional)" value="{{ old('file_name') }}" hint="The original file name is used if left blank." />
                <x-input name="expiry_date" type="date" label="Expiry date (optional)" value="{{ old('expiry_date') }}" hint="Add it for insurance so renewals are flagged in time." />
                <x-button type="submit"><x-icon name="lock" class="size-4" />Upload privately</x-button>
            </form>
        </x-card>
        <x-card class="document-files-card">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="document-card-kicker">Review your records</p><h2 class="text-lg font-black text-brand-700">Stored files</h2><p class="mt-1 text-sm text-stone-600">Preview or download a private copy whenever you need it.</p></div><x-badge>{{ $owner->documents->count() }} total</x-badge></div>
            <div class="mt-5 space-y-3">
                @forelse ($owner->documents as $document)
                    @php
                        $expired = $document->expiry_date && $document->expiry_date->lt(today());
                        $expiring = ! $expired && $document->expiry_date && $document->expiry_date->lte(today()->addDays(30));
                    @endphp
                    <div @class(['document-file-row rounded-md border-2 p-4', 'is-archived border-stone-300 bg-stone-50' => $document->archived, 'is-expired border-red-700 bg-red-50' => $expired && ! $document->archived, 'is-expiring border-yellow-700 bg-yellow-50' => $expiring && ! $document->archived, 'border-ink bg-white' => ! $document->archived && ! $expired && ! $expiring])>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-1 gap-3"><x-icon name="file" class="mt-0.5 size-6 text-brand-700" /><div class="min-w-0"><p class="break-words font-bold">{{ $document->file_name ?: $document->file_type }}</p><p class="mt-1 text-xs text-stone-600">{{ $document->file_type }} · uploaded {{ $document->upload_date?->format('M j, Y') }}@if ($document->expiry_date) · <span @class(['font-bold text-red-700' => $expired && ! $document->archived, 'font-bold text-amber-800' => $expiring && ! $document->archived])>{{ $expired ? 'expired' : 'expires' }} {{ $document->expiry_date->format('M j, Y') }}</span>@endif</p></div></div>
                            <div class="flex flex-wrap gap-2">@if ($document->archived)<x-badge>Archived</x-badge>@elseif ($expired)<x-badge tone="error">Expired</x-badge>@elseif ($expiring)<x-badge tone="warning">Expiring</x-badge>@else<x-badge tone="success">Current</x-badge>@endif</div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                            <a class="text-link" href="{{ route('documents.preview', $document) }}" target="_blank" rel="noreferrer">Preview</a>
                            <a class="text-link" href="{{ route('documents.download', $document) }}">Download</a>
                            @if ($adminView && ! $document->archived)<form method="POST" action="{{ route('admin.contractors.documents.archive', [$owner, $document]) }}">@csrf<x-button type="submit" variant="secondary">Archive</x-button></form>@endif
                            @if ($adminView && ! $document->privateFile && ! $document->archived)<form method="POST" action="{{ route('admin.contractors.documents.destroy', [$owner, $document]) }}" onsubmit="return confirm('Permanently delete this document? This cannot be undone.');">@csrf @method('DELETE')<x-button type="submit" variant="danger">Delete</x-button></form>@endif
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="file" title="No documents yet">Upload a W9, insurance certificate or other permitted file.</x-empty-state>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
