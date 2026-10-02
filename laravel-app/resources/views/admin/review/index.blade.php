<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Contractor review queue">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="page-eyebrow">Account review</p><h1 class="page-title">Contractor review queue</h1><p class="mt-3 max-w-2xl leading-7 text-stone-600">Review registration details, verification, services and documents before granting approval. Approval and email verification remain separate requirements for work access.</p></div>
        <x-button href="{{ route('admin.contractors.index') }}" variant="secondary">All contractors</x-button>
    </div>
    <x-card class="mt-7">
        <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Name, company, email or phone" />
            <div><label for="approval" class="mb-2 block text-sm font-medium text-stone-700">Approval</label><select id="approval" name="approval" class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base"><option value="">Both review states</option><option value="0" @selected(isset($filters['approval']) && (int) $filters['approval'] === 0)>Not approved</option><option value="2" @selected(isset($filters['approval']) && (int) $filters['approval'] === 2)>Pending admin review</option></select></div>
            <label class="flex min-h-12 items-center gap-2 self-end text-sm"><input type="checkbox" name="priority" value="1" @checked($filters['priority'] ?? false) class="size-4 rounded border-stone-300 text-brand-700"> Priority contractors only</label>
            <div class="flex flex-wrap items-end gap-3"><x-button type="submit" variant="secondary">Filter</x-button><a href="{{ route('admin.review.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700">Clear filters</a></div>
            <fieldset class="sm:col-span-2 lg:col-span-4"><legend class="mb-2 text-sm font-medium text-stone-700">Must offer all selected services</legend><div class="flex flex-wrap gap-x-5 gap-y-3">@foreach ($services as $service)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" @checked(in_array($service->service_id, $filters['service_ids'] ?? [])) class="size-4 rounded border-stone-300 text-brand-700">{{ $service->service_name }}</label>@endforeach</div></fieldset>
        </form>
    </x-card>
    <p class="mt-6 text-sm text-stone-600">{{ $contractors->total() }} eligible account(s). Select accounts explicitly; bulk actions affect only the checked accounts on this page.</p>
    @if ($errors->any())<div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('admin.review.bulk') }}" class="mt-4">
        @csrf
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse ($contractors as $contractor)
                @php
                    $signals = $contractor->accountState?->registration_signals ?? [];
                    $domain = str_contains((string) $contractor->email, '@') ? substr($contractor->email, strrpos($contractor->email, '@') + 1) : null;
                    $w9 = $contractor->documents->contains(fn ($document) => strcasecmp((string) $document->file_type, 'W9') === 0);
                    $insurance = $contractor->documents->filter(fn ($document) => strcasecmp((string) $document->file_type, 'Insurance') === 0);
                @endphp
                <x-card>
                    <div class="flex items-start gap-3"><input id="contractor-{{ $contractor->user_id }}" aria-label="Select {{ $contractor->first_name }} {{ $contractor->last_name }}" type="checkbox" name="contractor_ids[]" value="{{ $contractor->user_id }}" class="mt-1 size-5 shrink-0 rounded border-stone-300 text-brand-700"><div class="min-w-0 flex-1"><label for="contractor-{{ $contractor->user_id }}" class="block break-words text-lg font-semibold">{{ $contractor->first_name }} {{ $contractor->last_name }}</label><p class="mt-1 break-words text-sm text-stone-600">{{ $contractor->company_name }}</p></div></div>
                    <div class="mt-4 flex flex-wrap gap-2"><x-badge tone="warning">{{ $contractor->is_approved === 2 ? 'Pending admin review' : 'Not approved' }}</x-badge><x-badge :tone="$contractor->hasVerifiedEmail() ? 'success' : 'neutral'">{{ $contractor->hasVerifiedEmail() ? 'Email verified' : 'Email unverified' }}</x-badge>@if ($contractor->priority)<x-badge tone="warning">Priority</x-badge>@endif</div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-stone-600">Registered</dt><dd>{{ $contractor->created_at?->format('M j, Y') ?: 'Unknown' }}</dd></div><div><dt class="text-stone-600">Email domain</dt><dd class="break-all">{{ $domain ?: 'No sign-in email' }}</dd></div><div class="sm:col-span-2"><dt class="text-stone-600">Contact</dt><dd class="break-all">{{ $contractor->email ?: 'No email' }} · {{ $contractor->company_phone ?: $contractor->phone1 ?: 'No phone' }}</dd></div><div class="sm:col-span-2"><dt class="text-stone-600">Services</dt><dd class="break-words">{{ $contractor->services->pluck('service_name')->join(', ') ?: 'No services selected' }}</dd></div><div><dt class="text-stone-600">W9</dt><dd>{{ $w9 ? 'On file' : 'Missing W9' }}</dd></div><div><dt class="text-stone-600">Insurance</dt><dd>@forelse($insurance as $document)<span class="block">{{ $document->expiry_date ? 'Expires '.$document->expiry_date->format('M j, Y') : 'On file · no expiry recorded' }}</span>@empty Missing insurance @endforelse</dd></div></dl>
                    <details class="mt-4 rounded-xl bg-stone-50 p-3"><summary class="cursor-pointer text-sm font-semibold text-stone-700">Private registration indicators ({{ count($signals) }})</summary><p class="mt-2 text-xs leading-5 text-stone-600">Indicators support manual review; they do not prove an account is spam.</p>@if ($signals)<pre class="mt-2 whitespace-pre-wrap break-all text-xs text-stone-700">{{ json_encode($signals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@else<p class="mt-2 text-sm text-stone-600">No stored indicators.</p>@endif</details>
                    <div class="mt-5 flex flex-wrap gap-3"><x-button href="{{ route('admin.contractors.show', $contractor) }}" variant="secondary">Review account</x-button><a class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700" href="{{ route('admin.contractors.documents.index', $contractor) }}">Review documents</a></div>
                </x-card>
            @empty
                <x-empty-state icon="people" title="No accounts awaiting review">Try another filter or open the contractor directory.</x-empty-state>
            @endforelse
        </div>
        @if ($contractors->isNotEmpty())
            <x-card class="mt-6">
                <h2 class="text-lg font-black text-brand-700">Act on selected accounts</h2><p class="mt-2 text-sm leading-6 text-stone-600">Approve grants work access after email verification. Reject sets the account to not approved. Archive retains its records and disables access. Block stops sign-in and revokes sessions; a private reason is required. If any selected account has changed or is ineligible, the whole action is cancelled.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label for="action" class="mb-2 block text-sm font-medium">Action</label><select id="action" name="action" required class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base"><option value="">Choose an action</option><option value="approve">Approve selected</option><option value="reject">Set selected to not approved</option><option value="block">Block access</option><option value="archive">Archive selected</option></select></div><x-input name="reason" label="Review reason (required to block)" maxlength="1000" :value="old('reason')" /></div>
                <label class="mt-4 flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="confirmed" value="1" required class="mt-1 size-4 shrink-0 rounded border-stone-300 text-brand-700">I reviewed the selected accounts and confirm this action.</label><x-button type="submit" class="mt-4">Apply to selected accounts</x-button>
            </x-card>
        @endif
    </form>
    <div class="mt-7">{{ $contractors->links() }}</div>
</x-app-layout>
