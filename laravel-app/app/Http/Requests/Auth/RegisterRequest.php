<?php

namespace App\Http\Requests\Auth;

use App\Actions\Identity\RegistrationBotCheck;
use App\Rules\NewPassword;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterRequest extends LoginRequest
{
    public function authorize(): bool
    {
        return (bool) config('identity.registration_enabled');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'], 'company_address' => ['required', 'string', 'max:255'],
            'company_phone' => ['required', 'string', 'max:50', 'regex:/^[+0-9() .-]{7,50}$/'],
            'phone1' => ['nullable', 'string', 'max:50'], 'phone2' => ['nullable', 'string', 'max:50'],
            'phone1_type' => ['nullable', Rule::in(['Mobile', 'Office', 'Home', 'Other'])],
            'phone2_type' => ['nullable', Rule::in(['Mobile', 'Office', 'Home', 'Other'])],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('Users', 'email')],
            'password' => ['required', 'string', 'confirmed', new NewPassword],
            'services' => ['sometimes', 'array', 'max:100'], 'services.*' => ['integer', 'distinct', Rule::exists('Services', 'service_id')],
            'bot_token' => ['required', 'string', 'max:100'], 'bot_answer' => ['nullable', 'string', 'max:10'],
            'website' => ['nullable', 'string', 'max:255'], 'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
            'is_admin' => ['prohibited'], 'is_approved' => ['prohibited'], 'user_id' => ['prohibited'], 'priority' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->any() && ! app(RegistrationBotCheck::class)->passes($this)) {
                $validator->errors()->add('bot_answer', 'Please complete the new security check and try again.');
            }
        }];
    }
}
