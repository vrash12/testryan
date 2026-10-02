<?php

// Local verification helper: serve only public/ beneath /contractors/.
// Run: php -S 127.0.0.1:8012 -t public scripts/serve-subdirectory.php
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$prefix = '/contractors';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path !== $prefix && ! str_starts_with($path, $prefix.'/')) {
    http_response_code(404);
    exit('Not found');
}

$publicRoot = realpath(__DIR__.'/../public');
$relative = substr($path, strlen($prefix));
$candidate = realpath($publicRoot.$relative);
$allowedTypes = ['css' => 'text/css', 'js' => 'text/javascript', 'ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'txt' => 'text/plain'];
$extension = $candidate ? pathinfo($candidate, PATHINFO_EXTENSION) : '';
if ($candidate && str_starts_with($candidate, $publicRoot.DIRECTORY_SEPARATOR) && is_file($candidate) && isset($allowedTypes[$extension])) {
    header('Content-Type: '.$allowedTypes[$extension]);
    header('X-Content-Type-Options: nosniff');
    readfile($candidate);
    exit;
}

$_SERVER['SCRIPT_NAME'] = $prefix.'/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicRoot.DIRECTORY_SEPARATOR.'index.php';
$_SERVER['PHP_SELF'] = $prefix.'/index.php'.$relative;
// Simulate a mount-specific environment without changing the root app's .env.
putenv('APP_URL=http://127.0.0.1:8012'.$prefix);
putenv('SESSION_PATH='.$prefix);
require $publicRoot.'/index.php';
