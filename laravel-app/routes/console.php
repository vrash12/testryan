<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('background.enabled') && config('queue.default') === 'database') {
    if (config('background.insurance_enabled')) {
        Schedule::command('nestcrafters:notify-document-expiry --staged')
            ->dailyAt(config('background.insurance_time'))
            ->timezone(config('app.timezone'))
            ->withoutOverlapping(60);
    }

    Schedule::command('nestcrafters:retry-identity-mail --queue')
        ->everyMinute()->withoutOverlapping(5);

    // cPanel cron can run this bounded worker without a process supervisor.
    if (config('background.shared_host_worker')) {
        Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=45 --sleep=1 --tries=3 --timeout=30')
            ->everyMinute()->withoutOverlapping(5);
    }
}
