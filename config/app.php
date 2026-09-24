<?php
/**
 * Application configuration - auto-detects base URL from folder name.
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Smart Forest Monitoring Platform');
    define('APP_VERSION', '2.0.0');
}

if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $base = $docRoot ? str_replace($docRoot, '', $appRoot) : '/dbms';
    define('BASE_URL', rtrim($base, '/'));
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}
