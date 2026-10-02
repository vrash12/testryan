<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTransition extends LegacyRecord
{
    protected $table = 'app_job_transitions';

    protected $guarded = [];

    public $timestamps = true;

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'from_state' => 'array', 'to_state' => 'array'];
    }
}
