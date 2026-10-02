<?php

namespace App\Jobs;

use App\Actions\Identity\DeliverIdentityMessage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverNotificationDelivery implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function __construct(public string $deliveryId) {}

    public function handle(DeliverIdentityMessage $deliver): void
    {
        $deliver->run($this->deliveryId);
    }
}
