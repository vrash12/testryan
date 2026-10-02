<?php

return [
    'registration_enabled' => env('REGISTRATION_ENABLED', true),
    'registration_admin_mail' => env('REGISTRATION_ADMIN_MAIL', true),
    'bot_driver' => env('REGISTRATION_BOT_DRIVER', 'challenge'),
    'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
    'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY'),
    'minimum_form_seconds' => 2,
    'challenge_minutes' => 15,
    'verification_minutes' => 60,
];
