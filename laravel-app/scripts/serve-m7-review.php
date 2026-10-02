<?php

// Local visual-review server for the disposable M7 dataset.
// Run from laravel-app after preparing the fixture:
//   php -S 127.0.0.1:8013 -t public scripts/serve-m7-review.php
// The process selects only m2_test and uses a separate session cookie.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$publicRoot = realpath(__DIR__.'/../public');
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$candidate = realpath($publicRoot.$requestPath);
$allowedTypes = ['css' => 'text/css', 'js' => 'text/javascript', 'ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'txt' => 'text/plain'];
$extension = $candidate ? pathinfo($candidate, PATHINFO_EXTENSION) : '';
if ($candidate && str_starts_with($candidate, $publicRoot.DIRECTORY_SEPARATOR) && is_file($candidate) && isset($allowedTypes[$extension])) {
    header('Content-Type: '.$allowedTypes[$extension]);
    header('X-Content-Type-Options: nosniff');
    readfile($candidate);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicRoot.DIRECTORY_SEPARATOR.'index.php';
$_SERVER['PHP_SELF'] = '/index.php'.$requestPath;
putenv('APP_ENV=local');
putenv('APP_URL=http://127.0.0.1:8013');
putenv('DB_CONNECTION=m2_test');
putenv('SESSION_COOKIE=nestcrafters_m7_review');
putenv('SESSION_PATH=/');
require $publicRoot.'/index.php';
