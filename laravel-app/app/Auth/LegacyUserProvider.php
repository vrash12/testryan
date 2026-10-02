<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class LegacyUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $user = parent::retrieveByCredentials($credentials);

        return $user instanceof User && $user->isActiveAccount() && $user->role() !== null ? $user : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
        // PHP-compatible bcrypt/Argon hashes; Laravel still controls guards, sessions and rehashing.
        return $user instanceof User && $user->isActiveAccount() && is_string($credentials['password'] ?? null)
            && password_verify($credentials['password'], $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        $plain = $credentials['password'] ?? null;
        // Keep a compatible historical hash if bcrypt cannot represent the complete credential.
        if (! is_string($plain) || strlen($plain) > 72 || str_contains($plain, "\0")) {
            return;
        }

        parent::rehashPasswordIfRequired($user, $credentials, $force);
    }
}
