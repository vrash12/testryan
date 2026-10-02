<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\JobMedia;
use App\Models\User;

class JobMediaPolicy extends DomainPolicy
{
    public function view(User $user, JobMedia $media, ?Job $parent = null): bool
    {
        $job = $media->job()->first();

        return $job !== null && (! $parent || (int) $parent->job_id === (int) $media->job_id) && app(JobPolicy::class)->view($user, $job);
    }

    public function create(User $user, Job $job): bool
    {
        return app(JobPolicy::class)->uploadMedia($user, $job);
    }

    public function delete(User $user, JobMedia $media): bool
    {
        return $this->admin($user);
    }
}
