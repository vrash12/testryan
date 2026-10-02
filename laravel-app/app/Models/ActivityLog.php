<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends LegacyRecord
{
    protected $table = 'ActivityLogs';

    protected $primaryKey = 'log_id';

    public function associatedUser(): BelongsTo
    {
        // A missing/deleted legacy user yields null, never a fabricated identity.
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }
}
