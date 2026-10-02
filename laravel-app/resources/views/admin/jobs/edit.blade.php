<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Edit job">
    <div class="max-w-4xl">
        <p class="page-eyebrow">Jobs</p>
        <h1 class="page-title">Edit {{ $job->job_name }}</h1>
        <p class="mt-3 leading-7 text-stone-600">Review descriptions, terms and financial details carefully before saving.</p>
        <x-card class="mt-7">@include('admin.jobs._form', ['action' => route('admin.jobs.update', $job), 'cancelUrl' => route('admin.jobs.show', $job), 'submitLabel' => 'Save job'])</x-card>
    </div>
</x-app-layout>
