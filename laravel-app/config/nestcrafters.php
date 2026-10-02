<?php

return [
    // Both this flag and APP_ENV=local are required; previews never authenticate.
    'shell_previews' => env('APP_SHELL_PREVIEWS', false),
    'navigation' => [
        'admin' => ['Dashboard', 'Review queue', 'Contractors', 'Jobs', 'Services', 'Documents', 'Templates', 'Activity', 'Notifications', 'Account access'],
        'contractor' => ['Dashboard', 'My jobs', 'Documents', 'Profile & services', 'Notifications'],
    ],
];
