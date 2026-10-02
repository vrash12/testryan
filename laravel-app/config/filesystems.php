<?php

return [
    'default' => env('FILESYSTEM_DISK', 'private'),
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => env('PRIVATE_STORAGE_ROOT', storage_path('app/private')),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
        ],
    ],
    // Later uploads require authenticated controller responses.
    'links' => [],
];
