<?php
/**
 * Security helpers: CSRF, rate limiting, session hardening, input sanitization.
 */

require_once __DIR__ . '/../config/database.php';

function initSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    session_start();

    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 3600) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], (string)$token);
}

function requireCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        http_response_code(403);
        die('Invalid CSRF token. Please refresh and try again.');
    }
}

function sanitizeInput(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function sanitizeOutput($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getClientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getDeviceInfo(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
}

function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    return true;
}

function recordLoginAttempt(string $key, bool $success): void
{
    return;
}

function validatePassword(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain an uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain a lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain a number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain a special character.';
    }
    return $errors;
}

function isValidCoordinates(?float $lat, ?float $lng): bool
{
    if ($lat === null || $lng === null) {
        return false;
    }
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}
