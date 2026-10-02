<x-guest-layout title="Contractor registration">
    <div class="mx-auto max-w-2xl">
        <p class="page-eyebrow">Join the team</p>
        <h1 class="page-title">Create your contractor account</h1>
        <p class="page-lede">Tell us about your company and services. You’ll verify your email, then an administrator reviews your account before work access opens.</p>
        <ol class="mt-6 grid gap-2 text-sm font-bold sm:grid-cols-3" aria-label="Registration steps">
            <li class="flex items-center gap-2 rounded-md border-2 border-ink bg-signal-yellow px-3 py-2"><span class="flex size-6 items-center justify-center rounded-full border-2 border-ink bg-white text-xs">1</span>Your details</li>
            <li class="flex items-center gap-2 rounded-md border-2 border-dashed border-stone-400 px-3 py-2 text-stone-600"><span class="flex size-6 items-center justify-center rounded-full border-2 border-stone-400 text-xs">2</span>Verify email</li>
            <li class="flex items-center gap-2 rounded-md border-2 border-dashed border-stone-400 px-3 py-2 text-stone-600"><span class="flex size-6 items-center justify-center rounded-full border-2 border-stone-400 text-xs">3</span>Admin approval</li>
        </ol>
        <x-card class="mt-8">
            <x-feedback />
            <form method="POST" action="{{ route('register.store') }}" class="space-y-7">
                @csrf
                <input type="hidden" name="bot_token" value="{{ $challenge['token'] }}">
                <div class="hidden" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <fieldset class="space-y-5">
                    <legend class="mb-4 flex w-full items-center gap-3 border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Your details</legend>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="first_name" label="First name" autocomplete="given-name" :value="old('first_name')" maxlength="100" required />
                        <x-input name="last_name" label="Last name" autocomplete="family-name" :value="old('last_name')" maxlength="100" required />
                    </div>
                    <x-input name="email" label="Email address" type="email" autocomplete="email" :value="old('email')" maxlength="255" required />
                    <x-input name="password" label="Password" type="password" autocomplete="new-password" hint="Use a passphrase of at least 12 characters." required />
                    <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
                </fieldset>
                <fieldset class="space-y-5">
                    <legend class="mb-4 flex w-full items-center gap-3 border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Company</legend>
                    <x-input name="company_name" label="Company name" autocomplete="organization" :value="old('company_name')" maxlength="255" required />
                    <x-input name="company_address" label="Company address" autocomplete="street-address" :value="old('company_address')" maxlength="255" required />
                    <x-input name="company_phone" label="Company phone" type="tel" autocomplete="tel" :value="old('company_phone')" maxlength="50" required />
                    @foreach (['phone1' => 'Additional phone', 'phone2' => 'Second additional phone'] as $phone => $label)
                        <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                            <x-input :name="$phone" :label="$label.' (optional)'" type="tel" :value="old($phone)" maxlength="50" />
                            <div><label for="{{ $phone }}_type" class="mb-2 block text-sm font-bold text-ink">Phone type</label>
                                <select id="{{ $phone }}_type" name="{{ $phone }}_type" class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 text-base">
                                    <option value="">Select type</option>
                                    @foreach (['Mobile', 'Office', 'Home', 'Other'] as $type)<option @selected(old($phone.'_type') === $type)>{{ $type }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </fieldset>
                <fieldset id="services" class="space-y-4">
                    <legend class="mb-4 flex w-full items-center gap-3 border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Services (optional)</legend>
                    <p class="text-sm text-stone-600">Select the services your company offers.</p>
                    <label for="service-filter" class="sr-only">Filter services</label>
                    <input id="service-filter" type="search" placeholder="Find a service" class="min-h-12 w-full rounded-xl border border-stone-300 px-3 text-base" data-service-filter>
                    <div class="max-h-72 space-y-1 overflow-y-auto rounded-md border-2 border-ink bg-white p-2" data-service-options>
                        @forelse ($services as $service)
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-md border-2 border-transparent px-3 py-2 hover:bg-mist has-[:checked]:border-ink has-[:checked]:bg-brand-50 has-[:checked]:font-bold" data-service-name="{{ $service->service_name }}">
                                <input type="checkbox" name="services[]" value="{{ $service->service_id }}" @checked(in_array($service->service_id, (array) old('services', []))) class="size-5 shrink-0 accent-brand-700">
                                <span class="text-sm">{{ $service->service_name }}</span>
                            </label>
                        @empty<p class="p-3 text-sm text-stone-600">Services will be listed here when available.</p>@endforelse
                    </div>
                    <p class="text-sm font-semibold text-brand-700" data-service-count aria-live="polite">Selections are saved with your registration.</p>
                </fieldset>
                <fieldset class="space-y-4">
                    <legend class="mb-4 flex w-full items-center gap-3 border-b-2 border-ink pb-2 text-lg font-black text-brand-700">Security check</legend>
                    @if (config('identity.bot_driver') === 'turnstile' && config('identity.turnstile_site_key'))
                        <div id="bot_answer" class="cf-turnstile" data-sitekey="{{ config('identity.turnstile_site_key') }}" data-action="registration" data-size="flexible"></div>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        @error('bot_answer')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    @elseif (app()->environment(['local', 'testing']) && config('identity.bot_driver') === 'challenge')
                        <x-input name="bot_answer" :label="$challenge['question']" inputmode="numeric" autocomplete="off" required />
                    @else
                        <x-alert tone="error">Registration is temporarily unavailable while the security check is configured.</x-alert>
                    @endif
                </fieldset>
                <x-button type="submit" class="w-full">Create account</x-button>
                <p class="text-sm text-stone-600">Already registered? <a href="{{ route('login') }}" class="text-link">Sign in</a></p>
            </form>
        </x-card>
    </div>
</x-guest-layout>
