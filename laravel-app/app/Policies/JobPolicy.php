<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy extends DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->admin($user) || $user->canAccessWork();
    }

    public function view(User $user, Job $job): bool
    {
        return $this->admin($user) || $this->owner($user, $job->contractor_id);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Job $job): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->admin($user);
    }

    public function accept(User $user, Job $job): bool
    {
        if ($this->admin($user)) {
            return $job->contractor_id !== null && $job->is_completed === 0;
        }

        return $this->owner($user, $job->contractor_id)
            && $job->is_completed === 0;
    }

    public function complete(User $user, Job $job): bool
    {
        return $this->admin($user);
    }

    public function uploadMedia(User $user, Job $job): bool
    {
        // Completion is enforced by the write action so the legacy policy
        // remains compatible with already-completed accepted jobs that may
        // still need administrative/media inspection.
        return $this->admin($user) || ($this->owner($user, $job->contractor_id) && $job->is_accepted === 1);
    }
}
