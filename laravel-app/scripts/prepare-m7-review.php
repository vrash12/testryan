<?php

// Convenience wrapper for local visual review. Run from laravel-app:
//   php scripts/prepare-m7-review.php
// It delegates to the guarded Artisan command; it never selects the normal
// nestcrafters_local connection and never contacts the legacy/production host.
passthru(PHP_BINARY.' '.escapeshellarg(__DIR__.'/../artisan').' m7:prepare-review', $exitCode);
exit($exitCode);
