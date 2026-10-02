<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends LegacyRecord
{
    protected $table = 'Jobs';

    protected $primaryKey = 'job_id';

    public function status(): JobStatus
    {
        if ($this->is_completed === 1) {
            return JobStatus::Completed;
        }
        if ($this->contractor_id === null) {
            return JobStatus::Unassigned;
        }

        return $this->is_accepted === 1 ? JobStatus::Accepted : JobStatus::Pending;
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contractor_id', 'user_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'Job_Services', 'job_id', 'service_id', 'job_id', 'service_id')->using(JobService::class)->withPivot('job_service_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(JobNote::class, 'job_id', 'job_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(JobMedia::class, 'job_id', 'job_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(JobTransition::class, 'job_id', 'job_id')->latest('created_at');
    }

    protected function casts(): array
    {
        return [
            'is_accepted' => 'integer', 'is_completed' => 'integer', 'contractor_id' => 'integer',
            'entered_datetime' => 'datetime', 'due_date' => 'date', 'accepted_datetime' => 'datetime',
            'completed_datetime' => 'datetime', 'approved_price' => 'decimal:2', 'deposit_amount' => 'decimal:2',
        ];
    }
}
