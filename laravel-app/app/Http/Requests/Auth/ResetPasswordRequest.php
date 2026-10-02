<?php

namespace App\Http\Requests\Auth;

use App\Rules\NewPassword;

class ResetPasswordRequest extends RecoveryRequest
{
    public function rules(): array
    {
        return parent::rules() + ['token' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'confirmed', new NewPassword]];
    }
}
