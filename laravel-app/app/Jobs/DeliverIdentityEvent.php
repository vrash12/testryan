<?php

namespace App\Jobs;

use App\Actions\Identity\DeliverIdentityMessage;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverIdentityEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $eventId) {}

    public function handle(DeliverIdentityMessage $deliver): void
    {
        foreach (NotificationDelivery::where('event_id', $this->eventId)->pluck('id') as $id) {
            // One provider request per worker job keeps shared-host timeouts bounded.
            if (config('queue.default') === 'database') {
                DeliverNotificationDelivery::dispatch($id);
            } else {
                $deliver->run($id);
            }
        }
    }
}
