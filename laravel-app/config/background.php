<?php

return [
    // Enable only on the configured hosting environment after migrations.
    'enabled' => env('NESTCRAFTERS_BACKGROUND_ENABLED', false),
    'shared_host_worker' => env('NESTCRAFTERS_SHARED_HOST_WORKER', true),
    'insurance_enabled' => env('NESTCRAFTERS_INSURANCE_SCHEDULE', false),
    // Uses APP_TIMEZONE for both the schedule and the command's business date.
    'insurance_time' => env('NESTCRAFTERS_INSURANCE_TIME', '09:00'),
];
