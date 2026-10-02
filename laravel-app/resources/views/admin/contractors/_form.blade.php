@php($isEdit = isset($contractor))
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-input name="first_name" label="First name" value="{{ old('first_name', $contractor->first_name ?? '') }}" required />
        <x-input name="last_name" label="Last name" value="{{ old('last_name', $contractor->last_name ?? '') }}" required />
        <x-input name="company_name" label="Company name" value="{{ old('company_name', $contractor->company_name ?? '') }}" required />
        <x-input name="company_phone" label="Company phone" value="{{ old('company_phone', $contractor->company_phone ?? '') }}" />
        <div class="sm:col-span-2"><x-input name="company_address" label="Company address" value="{{ old('company_address', $contractor->company_address ?? '') }}" required /></div>
        <x-input name="email" type="email" label="Sign-in email" value="{{ old('email', $contractor->email ?? '') }}" hint="Required for email verification and notifications." @if($isEdit) required @endif />
        <x-input name="phone1" label="Primary phone" value="{{ old('phone1', $contractor->phone1 ?? '') }}" />
        <x-input name="phone1_type" label="Primary phone type" value="{{ old('phone1_type', $contractor->phone1_type ?? 'Mobile') }}" required />
        <x-input name="phone2" label="Secondary phone" value="{{ old('phone2', $contractor->phone2 ?? '') }}" required />
        <x-input name="phone2_type" label="Secondary phone type" value="{{ old('phone2_type', $contractor->phone2_type ?? 'Office') }}" required />
        @if (! $isEdit)<x-input name="password" type="password" label="Temporary password" hint="Leave blank to generate a temporary password that must be set through account access." />@endif
        <x-input name="profile_photo" type="file" label="Profile photo" hint="JPEG, PNG, GIF or WebP up to 5 MB." accept="image/jpeg,image/png,image/gif,image/webp" />
    </div>
    <div><p class="mb-3 text-sm font-medium text-stone-700">Services</p><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($services as $service)<label class="flex min-h-12 items-center gap-3 rounded-xl border border-stone-200 px-3 text-sm"><input type="checkbox" name="services[]" value="{{ $service->service_id }}" @checked(in_array($service->service_id, old('services', isset($contractor) ? $contractor->services->pluck('service_id')->all() : []), true)) class="size-4 rounded border-stone-300 text-brand-700">{{ $service->service_name }}</label>@endforeach</div>@if($services->isEmpty())<p class="text-sm text-amber-800">Add a service to the catalog before assigning one.</p>@endif</div>
    <label class="flex min-h-12 items-center gap-3 text-sm text-stone-700"><input type="checkbox" name="priority" value="1" @checked(old('priority', $contractor->priority ?? false)) class="size-4 rounded border-stone-300 text-brand-700"> Mark this contractor as priority</label>
    <div class="flex flex-wrap gap-3"><x-button type="submit">{{ $submitLabel }}</x-button><x-button href="{{ $cancelUrl }}" variant="secondary">Cancel</x-button></div>
</form>
