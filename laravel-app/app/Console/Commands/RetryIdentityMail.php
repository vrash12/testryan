<?php

namespace App\Console\Commands;

use App\Actions\Identity\DeliverIdentityMessage;
use App\Jobs\DeliverNotificationDelivery;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;

class RetryIdentityMail extends Command
{
    protected $signature = 'nestcrafters:retry-identity-mail {--queue : Dispatch due deliveries to the persistent queue}';

    protected $description = 'Retry due identity and business deliveries without displaying addresses or tokens';

    public function handle(DeliverIdentityMessage $deliver): int
    {
        if ($this->option('queue') && config('queue.default') !== 'database') {
            $this->error('Queued retry requires the configured database queue.');

            return self::FAILURE;
        }
        $ids = NotificationDelivery::whereIn('status', ['pending', 'failed'])->where('available_at', '<=', now())->limit(100)->pluck('id');
        foreach ($ids as $id) {
            if ($this->option('queue')) {
                DeliverNotificationDelivery::dispatch($id);
            } else {
                $deliver->run($id);
            }
        }
        $this->info('Processed '.$ids->count().' due deliveries. Private delivery records retain the outcomes.');

        return self::SUCCESS;
    }
}
