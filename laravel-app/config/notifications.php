<?php

return [
    'inbox_enabled' => env('NESTCRAFTERS_NOTIFICATION_INBOX', false),
    // Business mail is opt-in until recipients, schedules and SMTP are accepted.
    'business_enabled' => env('NESTCRAFTERS_BUSINESS_NOTIFICATIONS', false),
    'events' => [
        'contractor' => [
            'profile_changed' => env('NOTIFY_CONTRACTOR_PROFILE_CHANGED', true),
            'approval_changed' => env('NOTIFY_CONTRACTOR_APPROVAL_CHANGED', true),
        ],
        'job' => [
            'assigned' => env('NOTIFY_JOB_ASSIGNED', true),
            'updated' => env('NOTIFY_JOB_UPDATED', true),
            'accepted' => env('NOTIFY_JOB_ACCEPTED', true),
            'completed' => env('NOTIFY_JOB_COMPLETED', true),
            'note_added' => env('NOTIFY_JOB_NOTE_ADDED', true),
            'media_uploaded' => env('NOTIFY_JOB_MEDIA_UPLOADED', true),
        ],
        'document' => ['expiring' => env('NOTIFY_DOCUMENT_EXPIRING', true)],
    ],
];
