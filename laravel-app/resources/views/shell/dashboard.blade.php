<x-app-layout :role="$role" :navigation="$navigation" :preview="$preview">
    <div class="mb-8">
        <p class="page-eyebrow">{{ $role === 'admin' ? 'Team overview' : 'Your workspace' }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ $role === 'admin' ? 'A clear view of your team.' : 'Your work, all in one place.' }}</h1>
        <p class="mt-3 max-w-xl leading-7 text-stone-600">{{ $role === 'admin' ? 'Keep contractors, documents and assigned work moving together.' : 'Stay on top of your assigned jobs, documents and profile.' }}</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($role === 'admin' ? ['Contractor approvals', 'Active jobs', 'Expiring insurance'] : ['Account status', 'Assigned jobs', 'Your documents'] as $index => $label)
            <x-card>
                <div class="flex items-center justify-between gap-2"><span class="text-sm font-medium text-stone-600">{{ $label }}</span><x-icon :name="['people', 'job', 'file'][$index]" class="text-stone-400" /></div>
                @php $statKey = $role === 'admin' ? ['contractors', 'jobs', 'expiring'][$index] : ['status', 'jobs', 'documents'][$index]; @endphp
                <p class="mt-6 text-3xl font-semibold text-stone-900">{{ $stats[$statKey] ?? '—' }}</p>
                <p class="mt-2 text-xs text-stone-600">{{ $role === 'admin' ? ['Pending review', 'Not completed', 'Due within 30 days'][$index] : ['Current access state', 'Not completed', 'Active files'][$index] }}</p>
            </x-card>
        @endforeach
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black text-brand-700">{{ $role === 'admin' ? 'Jobs at a glance' : 'Your upcoming work' }}</h2><x-badge>Coming soon</x-badge></div>
            <x-empty-state icon="job" title="Your job workspace is taking shape">Assigned work, due dates and status updates will appear here once job management is available.</x-empty-state>
        </x-card>
        <x-card>
            <h2 class="text-lg font-black text-brand-700">{{ $role === 'admin' ? 'Manage your workspace' : 'Keep your details up to date' }}</h2>
            <p class="mt-2 text-sm leading-6 text-stone-600">{{ $role === 'admin' ? 'Your management tools will be available here.' : 'Your account tools will be available here.' }}</p>
            <div class="mt-6 space-y-3">
                @if ($role === 'admin')
                    <x-button href="{{ route('admin.contractors.index') }}" variant="secondary" class="w-full justify-between">Review contractors <x-icon name="arrow" class="size-4" /></x-button>
                    <x-button href="{{ route('admin.services.index') }}" variant="secondary" class="w-full justify-between">Manage services <x-icon name="arrow" class="size-4" /></x-button>
                @elseif (! $preview)
                    <x-button href="{{ route('contractor.profile.edit', auth()->user()) }}" variant="secondary" class="w-full justify-between">Update profile & services <x-icon name="arrow" class="size-4" /></x-button>
                    <x-button href="{{ route('contractor.documents.index', auth()->user()) }}" variant="secondary" class="w-full justify-between">Upload documents <x-icon name="arrow" class="size-4" /></x-button>
                @else
                    <button disabled type="button" class="flex min-h-14 w-full items-center justify-between gap-3 rounded-xl border border-stone-200 px-4 text-left text-sm text-stone-600">Preview actions are disabled <x-icon name="lock" class="size-4" /></button>
                @endif
            </div>
        </x-card>
    </div>
</x-app-layout>
