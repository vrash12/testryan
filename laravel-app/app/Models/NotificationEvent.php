<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends LegacyRecord
{
    use HasUlids;

    protected $table = 'app_notification_events';

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'created_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'event_id');
    }
}
