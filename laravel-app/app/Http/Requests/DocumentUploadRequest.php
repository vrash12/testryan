<?php

namespace App\Http\Requests;

use App\Models\ContractorDocument;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = $this->route('user');

        return $owner instanceof User && Gate::allows('create', [ContractorDocument::class, $owner]);
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'file_type' => ['required', 'string', Rule::in(['W9', 'Insurance', 'Other'])],
            'file_name' => ['nullable', 'string', 'max:255'],
            // Historical or newly issued documents may already be expired; expiry is displayed and reviewed separately.
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
