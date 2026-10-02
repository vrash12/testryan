@php
    $isEdit = isset($job) && $job !== null;
    $selectedServices = array_map('intval', old('services', $isEdit ? $job->services->pluck('service_id')->all() : []));
@endphp
<form method="POST" action="{{ $action }}" class="space-y-7">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2"><x-input name="job_name" label="Job name" value="{{ old('job_name', $job->job_name ?? '') }}" maxlength="255" required /></div>
        <x-input name="due_date" type="date" label="Due date" value="{{ old('due_date', $job?->due_date?->format('Y-m-d')) }}" hint="Optional calendar date." />
        <x-input name="who_supplies_materials" label="Who supplies materials?" value="{{ old('who_supplies_materials', $job->who_supplies_materials ?? '') }}" maxlength="100" placeholder="Client, NestCrafters or contractor" />
        <x-input name="approved_price" type="number" label="Approved price" value="{{ old('approved_price', $job->approved_price ?? '') }}" min="0" max="99999999.99" step="0.01" hint="Leave blank if not agreed. Zero is saved as 0.00." />
        <x-input name="deposit_amount" type="number" label="Deposit amount" value="{{ old('deposit_amount', $job->deposit_amount ?? '') }}" min="0" max="99999999.99" step="0.01" hint="Must not exceed the approved price." />
    </div>
    <fieldset>
        <legend class="text-base font-semibold">Required services</legend>
        <p id="services-help" class="mt-1 text-sm text-stone-600">Select at least one service for this job.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-stone-200 px-3 py-2 text-sm">
                    <input type="checkbox" name="services[]" value="{{ $service->service_id }}" aria-describedby="services-help" @checked(in_array((int) $service->service_id, $selectedServices, true)) class="size-4 shrink-0 rounded border-stone-300 text-brand-700">
                    <span class="break-words">{{ $service->service_name }}</span>
                </label>
            @endforeach
        </div>
        @error('services')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @if ($services->isEmpty())<p class="mt-3 text-sm text-amber-800">Add a service to the catalog before creating a job.</p>@endif
    </fieldset>
    <div>
        <label for="job_description" class="mb-2 block text-sm font-medium text-stone-700">Description <span aria-hidden="true">*</span></label>
        <textarea id="job_description" name="job_description" rows="7" maxlength="100000" required aria-describedby="job_description-help" @if($errors->has('job_description')) aria-invalid="true" @endif class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base">{{ old('job_description', $job->job_description ?? '') }}</textarea>
        <p id="job_description-help" class="mt-2 text-sm {{ $errors->has('job_description') ? 'text-red-700' : 'text-stone-600' }}">{{ $errors->first('job_description') ?: 'Include location and scope of work. Supported formatting is rendered safely on the job page.' }}</p>
    </div>
    <div>
        <label for="terms" class="mb-2 block text-sm font-medium text-stone-700">Terms</label>
        <textarea id="terms" name="terms" rows="7" maxlength="100000" aria-describedby="terms-help" @if($errors->has('terms')) aria-invalid="true" @endif class="w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base">{{ old('terms', $job->terms ?? '') }}</textarea>
        <p id="terms-help" class="mt-2 text-sm {{ $errors->has('terms') ? 'text-red-700' : 'text-stone-600' }}">{{ $errors->first('terms') ?: 'Optional job terms and site guidelines.' }}</p>
    </div>
    @if(isset($templates) && $templates->isNotEmpty())
        <fieldset class="rounded-xl border border-stone-200 bg-stone-50 p-4">
            <legend class="px-2 font-semibold">Insert a job template</legend>
            <p class="mt-1 text-sm leading-6 text-stone-600">Choose a template and append it to a field. Existing draft text stays in place until you explicitly insert.</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-0 basis-full sm:flex-1"><label for="job-template" class="mb-2 block text-sm font-medium text-stone-700">Template</label><select id="job-template" class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base"><option value="">Select a Jobs template</option>@foreach($templates as $template)<option value="{{ $template->template_id }}">{{ $template->name }}</option>@endforeach</select></div>
                <button type="button" data-apply-template="job_description" class="min-h-11 rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold">Append to description</button>
                <button type="button" data-apply-template="terms" class="min-h-11 rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold">Append to terms</button>
            </div>
            <p id="template-apply-status" class="mt-3 text-sm text-stone-600" role="status" aria-live="polite"></p>
        </fieldset>
    @endif
    @if (! $isEdit)
        <fieldset class="rounded-xl border border-stone-200 p-4">
            <legend class="px-2 font-semibold">Initial assignment</legend>
            <label for="contractor_id" class="mb-2 block text-sm font-medium text-stone-700">Contractor</label>
            <select id="contractor_id" name="contractor_id" class="min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base">
                <option value="">Leave unassigned</option>
                @foreach ($contractors as $contractor)
                    <option value="{{ $contractor->user_id }}" @selected((string) old('contractor_id') === (string) $contractor->user_id)>{{ $contractor->company_name ?: $contractor->first_name.' '.$contractor->last_name }}{{ $contractor->priority ? ' · Priority' : '' }}</option>
                @endforeach
            </select>
            @error('contractor_id')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            <p class="mt-2 text-sm text-stone-600">Only active approved contractors are available.</p>
            <label class="mt-3 flex min-h-12 items-start gap-3 text-sm text-stone-700">
                <input type="checkbox" name="accept_on_behalf" value="1" @checked(old('accept_on_behalf')) class="mt-1 size-4 shrink-0 rounded border-stone-300 text-brand-700">
                <span>Accept on behalf of the selected contractor. The acceptance is recorded as an admin action.</span>
            </label>
            @error('accept_on_behalf')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </fieldset>
    @else
        <p class="rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-600">Assignment and job status have separate controls on the job page.</p>
    @endif
    <div class="flex flex-wrap gap-3"><x-button type="submit">{{ $submitLabel }}</x-button><x-button href="{{ $cancelUrl }}" variant="secondary">Cancel</x-button></div>
</form>
@if(isset($templates) && $templates->isNotEmpty())
<script>
document.querySelectorAll('[data-apply-template]').forEach((button) => button.addEventListener('click', async () => {
    const select = document.getElementById('job-template'), status = document.getElementById('template-apply-status'), id = select.value;
    if (!id) { status.textContent = 'Select a template first.'; return; }
    button.disabled = true; status.textContent = 'Loading template…';
    try {
        const url = @json(route('admin.templates.content', ['template' => '__TEMPLATE__']));
        const response = await fetch(url.replace('__TEMPLATE__', encodeURIComponent(id)), {headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Template unavailable');
        const data = await response.json(), field = document.getElementById(button.dataset.applyTemplate);
        field.value = field.value.trim() ? `${field.value}\n\n${data.html_content}` : data.html_content;
        field.dispatchEvent(new Event('input', {bubbles: true}));
        status.textContent = `Appended “${data.name}” to ${button.dataset.applyTemplate === 'terms' ? 'terms' : 'description'}.`;
    } catch (error) { status.textContent = 'The template could not be loaded. Your draft is unchanged.'; }
    button.disabled = false;
}));
</script>
@endif
