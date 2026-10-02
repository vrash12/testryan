<?php

namespace App\Http\Requests;

use App\Models\Job;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JobAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof Job && Gate::allows('update', $job);
    }

    public function rules(): array
    {
        return [
            'contractor_id' => ['nullable', 'integer', Rule::exists('Users', 'user_id')],
        ];
    }
}
