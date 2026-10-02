<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Check PHP version requirement for Laravel 11/12
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    header('HTTP/1.1 500 Internal Server Error');
    die('<h3>PHP Version Incompatibility</h3><p>Current PHP version: <strong>' . htmlspecialchars(PHP_VERSION) . '</strong></p><p>Laravel 11 requires <strong>PHP 8.2 or higher</strong>. Please go to your InfinityFree Control Panel &rarr; <strong>Select PHP Version</strong> and set it to <strong>PHP 8.3</strong>.</p>');
}

// Errors are routed through Laravel (bootstrap/app.php) so that /api/*
// responses stay valid JSON. Never write to output here: a stray notice on a
// JSON endpoint corrupts the payload, and in production a stack trace leaks
// the database credentials.

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
