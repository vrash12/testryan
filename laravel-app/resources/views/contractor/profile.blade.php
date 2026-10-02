<x-app-layout role="contractor" :navigation="config('nestcrafters.navigation.contractor')" title="Profile & services">
    <div class="max-w-4xl">
        <x-page-header eyebrow="Your account" title="Profile & services">Keep your contact details and services current so the right work reaches you.</x-page-header>

        <div class="callout callout-warning mt-6" role="note">
            <x-icon name="alert" class="size-6" />
            <p class="min-w-0 flex-1 text-sm leading-6"><span class="font-black">Saving sends your account back to admin review.</span> Work access pauses until an administrator approves the updated profile or service selection.</p>
        </div>

        <x-card class="mt-6"><x-feedback />
            <form method="POST" action="{{ route('contractor.profile.update', $contractor) }}" enctype="multipart/form-data" class="space-y-8">@csrf @method('PUT')
                <fieldset>
                    <legend class="mb-4 w-full border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Your details</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="first_name" label="First name" autocomplete="given-name" value="{{ old('first_name', $contractor->first_name) }}" required />
                        <x-input name="last_name" label="Last name" autocomplete="family-name" value="{{ old('last_name', $contractor->last_name) }}" required />
                        <div class="sm:col-span-2"><x-input name="email" type="email" label="Email" autocomplete="email" value="{{ old('email', $contractor->email) }}" required /></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-4 w-full border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Company</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="company_name" label="Company name" autocomplete="organization" value="{{ old('company_name', $contractor->company_name) }}" required />
                        <x-input name="company_phone" type="tel" label="Company phone" autocomplete="tel" value="{{ old('company_phone', $contractor->company_phone) }}" />
                        <div class="sm:col-span-2"><x-input name="company_address" label="Company address" autocomplete="street-address" value="{{ old('company_address', $contractor->company_address) }}" required /></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-4 w-full border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Phone numbers</legend>
                    <datalist id="phone-types">@foreach (['Mobile', 'Office', 'Home', 'Other'] as $type)<option value="{{ $type }}"></option>@endforeach</datalist>
                    <div class="grid gap-5 sm:grid-cols-[2fr_1fr]">
                        <x-input name="phone1" type="tel" label="Primary phone" value="{{ old('phone1', $contractor->phone1) }}" />
                        <x-input name="phone1_type" label="Primary phone type" list="phone-types" value="{{ old('phone1_type', $contractor->phone1_type) }}" required />
                        <x-input name="phone2" type="tel" label="Secondary phone" value="{{ old('phone2', $contractor->phone2) }}" required />
                        <x-input name="phone2_type" label="Secondary phone type" list="phone-types" value="{{ old('phone2_type', $contractor->phone2_type) }}" required />
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-4 w-full border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Profile photo</legend>
                    <div class="flex flex-wrap items-start gap-5">
                        @if ($contractor->profile_photo)
                            <img src="{{ route('users.profile-photo', $contractor) }}" alt="Your profile photo" class="size-24 rounded-md border-2 border-ink object-cover">
                        @else
                            <span class="flex size-24 items-center justify-center rounded-md border-2 border-dashed border-stone-400 text-2xl font-black text-stone-600" aria-hidden="true">{{ mb_substr($contractor->first_name ?? '', 0, 1) }}{{ mb_substr($contractor->last_name ?? '', 0, 1) }}</span>
                        @endif
                        <div class="min-w-0 flex-1"><x-input name="profile_photo" type="file" label="Upload a new photo" accept="image/jpeg,image/png,image/gif,image/webp" hint="JPEG, PNG, GIF or WebP up to 5 MB." /></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-4 w-full border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Services</legend>
                    <p class="mb-3 text-sm text-stone-600">Select every service your company offers.</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach ($services as $service)<label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-md border-2 border-stone-300 bg-white px-3 text-sm hover:border-ink has-[:checked]:border-ink has-[:checked]:bg-brand-50 has-[:checked]:font-bold"><input type="checkbox" name="services[]" value="{{ $service->service_id }}" @checked(in_array($service->service_id, old('services', $contractor->services->pluck('service_id')->all()), true)) class="size-5 shrink-0">{{ $service->service_name }}</label>@endforeach</div>
                </fieldset>

                <div class="flex flex-wrap gap-3 border-t-2 border-ink pt-6"><x-button type="submit">Save and send for review</x-button><x-button href="{{ route('contractor.dashboard') }}" variant="secondary">Cancel</x-button></div>
            </form>
            @if ($contractor->profile_photo)
                <form method="POST" action="{{ route('contractor.profile.photo.destroy', $contractor) }}" class="mt-4">@csrf @method('DELETE')<x-button type="submit" variant="secondary">Remove current photo</x-button></form>
            @endif
        </x-card>
    </div>
</x-app-layout>
