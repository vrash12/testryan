<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobNote extends LegacyRecord
{
    protected $table = 'Job_Notes';

    protected $primaryKey = 'note_id';

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    protected function casts(): array
    {
        return ['created_datetime' => 'datetime'];
    }
}
