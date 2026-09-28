<?php

// Router for PHP's built-in web server (local dev only).
// Serves real files from public/ directly, everything else goes through Silverstripe.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . '/../public' . $path)) {
    return false;
}
require __DIR__ . '/../public/index.php';
