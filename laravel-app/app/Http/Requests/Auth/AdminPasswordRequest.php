<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\NewPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('user') instanceof User && Gate::allows('resetPassword', $this->route('user'));
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:1024'], 'password' => ['required', 'string', 'confirmed', new NewPassword]];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $validator->errors()->any() && ! password_verify($this->input('current_password'), $this->user()->getAuthPassword())) {
                $validator->errors()->add('current_password', 'Your current password could not be confirmed.');
            }
        }];
    }
}
