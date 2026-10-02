<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy extends DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, Template $template): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Template $template): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->admin($user);
    }
}
