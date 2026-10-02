<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends LegacyRecord
{
    use HasUlids;

    protected $table = 'app_notification_deliveries';

    protected $hidden = ['recipient_email', 'last_error'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'available_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'event_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id', 'user_id');
    }
}
