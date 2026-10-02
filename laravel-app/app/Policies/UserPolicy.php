<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends DomainPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->admin($actor);
    }

    public function view(User $actor, User $subject): bool
    {
        return $this->admin($actor) || $this->owner($actor, $subject->user_id);
    }

    public function create(User $actor): bool
    {
        return $this->admin($actor);
    }

    public function update(User $actor, User $subject): bool
    {
        return $this->view($actor, $subject);
    }

    public function approve(User $actor, User $subject): bool
    {
        return $this->admin($actor) && $subject->role() === 'contractor';
    }

    public function block(User $actor, User $subject): bool
    {
        return $this->admin($actor) && $subject->role() === 'contractor' && $actor->user_id !== $subject->user_id;
    }

    public function delete(User $actor, User $subject): bool
    {
        return $this->admin($actor) && $subject->role() === 'contractor';
    }

    public function resetPassword(User $actor, User $subject): bool
    {
        return $this->admin($actor) && $subject->isActiveAccount() && $subject->role() !== null;
    }
}
