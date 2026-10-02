<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy extends DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->admin($user) || $user->canAccessWork();
    }

    public function view(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->admin($user);
    }
}
