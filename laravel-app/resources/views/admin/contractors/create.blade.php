<x-app-layout role="admin" :navigation="config('nestcrafters.navigation.admin')" title="Add contractor">
    <div class="max-w-4xl"><p class="page-eyebrow">Contractors</p><h1 class="page-title">Add contractor</h1><p class="mt-3 leading-7 text-stone-600">New accounts start unapproved and must complete email verification before work access.</p><x-card class="mt-7"><x-feedback />@include('admin.contractors._form', ['action' => route('admin.contractors.store'), 'cancelUrl' => route('admin.contractors.index'), 'submitLabel' => 'Create contractor'])</x-card></div>
</x-app-layout>
