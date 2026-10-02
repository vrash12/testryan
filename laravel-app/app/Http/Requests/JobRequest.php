<?php

namespace App\Http\Requests;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof Job ? Gate::allows('update', $job) : Gate::allows('create', Job::class);
    }

    public function rules(): array
    {
        return [
            'job_name' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:100000'],
            'terms' => ['nullable', 'string', 'max:100000'],
            'due_date' => ['nullable', 'date'],
            'approved_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'deposit_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'who_supplies_materials' => ['nullable', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer', Rule::exists('Users', 'user_id')],
            'accept_on_behalf' => ['sometimes', 'boolean'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', 'distinct', Rule::exists('Services', 'service_id')],
        ];
    }
}
