<?php

namespace App\Policies;

use App\Models\ContractorNote;
use App\Models\User;

class ContractorNotePolicy extends DomainPolicy
{
    public function view(User $user, ContractorNote $note): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, ContractorNote $note): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, ContractorNote $note): bool
    {
        return $this->admin($user);
    }
}
