<?php

namespace App\Http\Requests\Auth;

class RecoveryRequest extends LoginRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:255']];
    }
}
