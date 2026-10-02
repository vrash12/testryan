<?php

namespace App\Policies;

use App\Models\ContractorDocument;
use App\Models\User;

class ContractorDocumentPolicy extends DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, ContractorDocument $file, ?User $parent = null): bool
    {
        return (! $parent || (int) $parent->user_id === (int) $file->user_id) && ($this->admin($user) || $this->owner($user, (int) $file->user_id));
    }

    public function create(User $user, User $owner): bool
    {
        return $this->admin($user) || $this->owner($user, $owner->user_id);
    }

    public function update(User $user, ContractorDocument $file): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, ContractorDocument $file): bool
    {
        return $this->admin($user);
    }
}
