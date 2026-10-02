<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\JobNote;
use App\Models\User;

class JobNotePolicy extends DomainPolicy
{
    public function view(User $user, JobNote $note, ?Job $parent = null): bool
    {
        $job = $note->job()->first();

        return $job !== null && (! $parent || (int) $parent->job_id === (int) $note->job_id) && app(JobPolicy::class)->view($user, $job);
    }

    public function create(User $user, Job $job): bool
    {
        return $this->admin($user)
            || ($this->owner($user, $job->contractor_id) && $job->is_completed === 0);
    }

    public function update(User $user, JobNote $note): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, JobNote $note): bool
    {
        return $this->admin($user);
    }
}
