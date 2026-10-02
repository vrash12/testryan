<x-guest-layout title="Welcome" wide>
    @php $registrationOpen = config('identity.registration_enabled'); @endphp

    <section class="home-hero" aria-labelledby="home-title">
        <div class="relative z-10 grid items-center gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <div>
                <p class="dash-eyebrow">NestCrafters contractor portal</p>
                <h1 id="home-title" class="mt-4 text-4xl font-black leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">Every job, every crew,<br class="hidden sm:block"> <span class="text-brand-orange">one clear workspace.</span></h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-white/85 sm:text-lg sm:leading-8">Assigned work, due dates, job photos and contractor documents in one place — built for the office and the job site.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="dash-action">Sign in <x-icon name="arrow" /></a>
                    @if ($registrationOpen)<a href="{{ route('register') }}" class="dash-action dash-action-light">Register as a contractor</a>@endif
                </div>
                <p class="mt-5 text-sm text-white/70">New contractor accounts are reviewed by an administrator before work access is granted.</p>
            </div>

            {{-- Illustrative example only: no live records are shown on the public page. --}}
            <div class="home-mock" aria-hidden="true">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="text-[11px] font-black uppercase tracking-widest text-stone-600">Example job</p><p class="mt-1 text-lg font-black text-brand-700">Kitchen remodel</p><p class="text-sm text-stone-600">Due in 5 days</p></div>
                    <x-badge tone="success">Accepted</x-badge>
                </div>
                <ol class="mt-5 grid grid-cols-3 gap-2 text-center text-xs font-bold">
                    <li class="rounded-md border-2 border-ink bg-emerald-100 px-2 py-2">Assigned</li>
                    <li class="rounded-md border-2 border-ink bg-emerald-100 px-2 py-2">Accepted</li>
                    <li class="rounded-md border-2 border-dashed border-stone-400 px-2 py-2 text-stone-600">Completed</li>
                </ol>
                <div class="mt-5 grid grid-cols-3 gap-2">
                    @foreach (['Before', 'During', 'After'] as $tag)
                        <div class="flex aspect-[4/3] flex-col items-center justify-center gap-1 rounded-md border-2 border-ink bg-mist text-[11px] font-bold uppercase tracking-wide text-brand-700"><x-icon name="camera" />{{ $tag }}</div>
                    @endforeach
                </div>
                <div class="mt-5 flex items-center justify-between gap-3 border-t-2 border-ink pt-4 text-sm"><span class="font-semibold">Approved price</span><span class="font-black tabular-nums">2,450.00</span></div>
            </div>
        </div>
    </section>

    <section class="mt-14 sm:mt-20" aria-labelledby="how-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><p class="page-eyebrow">Getting started</p><h2 id="how-title" class="mt-2 text-3xl font-black tracking-tight text-brand-700">From sign-up to your first job</h2></div>
            <p class="max-w-sm text-sm leading-6 text-stone-600">Each step is visible on your account status page, so you always know what's next.</p>
        </div>
        <ol class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Register', 'Tell us about your company, contact numbers and the services you offer.', 'profile'],
                ['Verify your email', 'Confirm your address from the message we send. This keeps accounts secure.', 'mail'],
                ['Admin approval', 'An administrator reviews your details. Profile or service changes are reviewed again.', 'status'],
                ['Accept & document work', 'Accept assigned jobs, then add before, during and after photos as you go.', 'job'],
            ] as [$step, $copy, $icon])
                <li class="home-step">
                    <div class="flex items-center justify-between"><span class="home-step-number">{{ $loop->iteration }}</span><x-icon :name="$icon" class="size-6 text-brand-700" /></div>
                    <h3 class="mt-4 text-lg font-black text-brand-700">{{ $step }}</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">{{ $copy }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-14 grid gap-6 sm:mt-20 lg:grid-cols-2" aria-label="What each role can do">
        <x-card class="sm:p-8">
            <span class="flex size-12 items-center justify-center rounded-md border-2 border-ink bg-brand-orange text-ink"><x-icon name="job" class="size-6" /></span>
            <h2 class="mt-5 text-2xl font-black tracking-tight text-brand-700">For contractors</h2>
            <p class="mt-2 text-sm leading-6 text-stone-600">Everything you need on site, on your phone.</p>
            <ul class="home-list mt-4">
                <li><x-icon name="check" />See assigned jobs with description, terms, services, price and deposit.</li>
                <li><x-icon name="check" />Accept a job, then upload tagged photos and PDFs from the site.</li>
                <li><x-icon name="check" />Ask questions and post updates in shared job notes.</li>
                <li><x-icon name="check" />Keep your W9 and insurance current and see when cover is due to expire.</li>
            </ul>
        </x-card>
        <x-card class="sm:p-8">
            <span class="flex size-12 items-center justify-center rounded-md border-2 border-ink bg-brand-700 text-paper"><x-icon name="people" class="size-6" /></span>
            <h2 class="mt-5 text-2xl font-black tracking-tight text-brand-700">For administrators</h2>
            <p class="mt-2 text-sm leading-6 text-stone-600">A daily view of the team, deadlines and paperwork.</p>
            <ul class="home-list mt-4">
                <li><x-icon name="check" />Review new registrations with duplicate and risk indicators.</li>
                <li><x-icon name="check" />Create, assign and track jobs — or accept on a contractor's behalf.</li>
                <li><x-icon name="check" />Spot missing W9s and insurance expiring within 30 days.</li>
                <li><x-icon name="check" />Follow every important change in the activity log.</li>
            </ul>
        </x-card>
    </section>

    <section class="callout callout-warning mt-14 sm:mt-20" aria-labelledby="ready-title">
        <span class="dash-step-icon"><x-icon name="file" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="ready-title" class="text-lg font-black">Have these ready before you register</h2>
            <p class="mt-1 text-sm leading-6 text-stone-800">Your company details, a W9, a current insurance certificate with its expiry date, and the services you offer. Job photos and documents can be JPEG, PNG or PDF, up to 10 MB each.</p>
        </div>
        @if ($registrationOpen)<x-button :href="route('register')" class="w-full sm:w-auto">Start registration <x-icon name="arrow" class="size-4" /></x-button>@endif
    </section>

    @if ($previewsEnabled)
        <section class="mt-14 border-t-2 border-dashed border-stone-400 pt-8" aria-labelledby="preview-title">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3"><h2 id="preview-title" class="text-lg font-black text-brand-700">Explore the application shell</h2><p class="text-sm text-stone-600">Local previews · no live accounts or data</p></div>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['admin' => ['Team management', 'An overview for contractors, approvals and assigned jobs.', 'people'], 'contractor' => ['Your contractor workspace', 'A focused home for work, documents and account details.', 'job']] as $role => [$label, $description, $icon])
                    <x-card>
                        <span class="flex size-12 items-center justify-center rounded-md border-2 border-ink bg-mist text-brand-700"><x-icon :name="$icon" class="size-6" /></span>
                        <h3 class="mt-5 text-xl font-black text-brand-700">{{ $label }}</h3>
                        <p class="mt-2 max-w-md text-sm leading-6 text-stone-600">{{ $description }}</p>
                        <x-button :href="route('local.preview', $role)" variant="secondary" class="mt-6">Preview {{ $role }} <x-icon name="arrow" class="size-4" /></x-button>
                    </x-card>
                @endforeach
            </div>
        </section>
    @endif
</x-guest-layout>
