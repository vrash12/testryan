<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Create job">
    <div class="max-w-4xl">
        <p class="page-eyebrow">Jobs</p>
        <h1 class="page-title">Create job</h1>
        <p class="mt-3 leading-7 text-stone-600">Define the work, services, dates and agreed amounts. Assign a contractor now or leave the job unassigned.</p>
        <x-card class="mt-7">@include('admin.jobs._form', ['action' => route('admin.jobs.store'), 'cancelUrl' => route('admin.jobs.index'), 'submitLabel' => 'Create job'])</x-card>
    </div>
</x-app-layout>
