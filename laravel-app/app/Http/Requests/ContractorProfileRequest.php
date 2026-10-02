<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContractorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && Gate::allows('update', $subject);
    }

    public function rules(): array
    {
        $subject = $this->route('user');
        $emailUnique = Rule::unique('Users', 'email');
        if ($subject instanceof User) {
            $emailUnique = $emailUnique->ignore($subject->user_id, 'user_id');
        }

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['required', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone1' => ['nullable', 'string', 'max:50'],
            'phone1_type' => ['required', 'string', 'max:75'],
            'phone2' => ['required', 'string', 'max:50'],
            'phone2_type' => ['required', 'string', 'max:75'],
            'email' => ['required', 'email:rfc', 'max:255', $emailUnique],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'distinct', Rule::exists('Services', 'service_id')],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('services')) {
            $this->merge(['services' => []]);
        }
    }
}
