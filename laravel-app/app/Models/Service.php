<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends LegacyRecord
{
    protected $table = 'Services';

    protected $primaryKey = 'service_id';

    public function contractors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ContractorServices', 'service_id', 'user_id', 'service_id', 'user_id')->using(ContractorService::class);
    }

    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'Job_Services', 'service_id', 'job_id', 'service_id', 'job_id')->using(JobService::class)->withPivot('job_service_id');
    }
}
