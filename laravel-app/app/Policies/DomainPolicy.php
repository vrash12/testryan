<?php

namespace App\Policies;

use App\Models\User;

abstract class DomainPolicy
{
    protected function admin(User $user): bool
    {
        return $user->role() === 'admin' && $user->isActiveAccount() && $user->hasVerifiedEmail();
    }

    protected function owner(User $user, ?int $owner): bool
    {
        return $user->canAccessWork() && $owner === (int) $user->user_id;
    }
}
