<?php
/**
 * AUTHENTICATION & AUTHORIZATION - FMS 2.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

initSecureSession();

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . url('pages/login.php'));
        exit;
    }
    validateSessionUser();
}

function validateSessionUser(): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        invalidateSession('Invalid session. Please log in again.');
    }

    ensureSimplifiedUserRoles();

    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT user_id, username, role, full_name FROM users WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        invalidateSession('Your session is outdated. Please log in again.');
    }

    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = normalizeRoleValue($user['role']);
    $_SESSION['full_name'] = $user['full_name'];
}

function invalidateSession(string $message): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = $message;
    header('Location: ' . url('pages/login.php'));
    exit;
}

function getSessionUserId(): int
{
    requireLogin();
    return (int)$_SESSION['user_id'];
}

function getUserRole(): ?string
{
    return isset($_SESSION['role']) ? normalizeRoleValue($_SESSION['role']) : null;
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    $role = getUserRole();
    $allowedRoles = array_map('normalizeRoleValue', $allowedRoles);
    if (!in_array($role, $allowedRoles, true)) {
        $_SESSION['flash_error'] = 'You do not have permission to access this page.';
        header('Location: ' . url('pages/dashboard.php'));
        exit;
    }
}

function isAdmin(): bool
{
    return getUserRole() === 'admin';
}

function isOfficer(): bool
{
    return getUserRole() === 'officer';
}

function isPublic(): bool
{
    return getUserRole() === 'public';
}

function canManageAnimals(): bool
{
    return in_array(getUserRole(), ['admin', 'officer'], true);
}

function canManageIncidents(): bool
{
    return in_array(getUserRole(), ['admin', 'officer'], true);
}

function canManageForests(): bool
{
    return in_array(getUserRole(), ['admin', 'officer'], true);
}

function canViewAnalytics(): bool
{
    return in_array(getUserRole(), ['admin', 'officer', 'public'], true);
}

function canExportData(): bool
{
    return in_array(getUserRole(), ['admin', 'officer'], true);
}

function canManageUsers(): bool
{
    return isAdmin();
}

function canManageReports(): bool
{
    return in_array(getUserRole(), ['admin', 'officer'], true);
}

function normalizeRoleValue(?string $role): string
{
    $role = strtolower(trim((string)$role));

    return match ($role) {
        'admin' => 'admin',
        'officer', 'forest_officer', 'wildlife_officer', 'data_analyst' => 'officer',
        default => 'public',
    };
}

function roleLabel(?string $role): string
{
    return match (normalizeRoleValue($role)) {
        'admin' => 'Admin',
        'officer' => 'Officer',
        default => 'Public',
    };
}

function ensureSimplifiedUserRoles(): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    $done = true;

    try {
        $pdo = getPDO();
        $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if (!$column) {
            return false;
        }

        $type = strtolower((string)($column['Type'] ?? ''));
        $hasLegacyRoles = str_contains($type, "'forest_officer'")
            || str_contains($type, "'wildlife_officer'")
            || str_contains($type, "'data_analyst'");
        $missingOfficer = !str_contains($type, "'officer'");

        if ($hasLegacyRoles || $missingOfficer) {
            $pdo->exec(
                "ALTER TABLE users MODIFY role ENUM('admin','officer','forest_officer','wildlife_officer','data_analyst','public') NOT NULL DEFAULT 'public'"
            );
            $pdo->exec(
                "UPDATE users SET role = 'officer' WHERE role IN ('forest_officer','wildlife_officer','data_analyst')"
            );
            $pdo->exec("UPDATE users SET role = 'public' WHERE role IS NULL OR role = ''");
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','officer','public') NOT NULL DEFAULT 'public'");
            return true;
        }

        $pdo->exec("UPDATE users SET role = 'public' WHERE role IS NULL OR role = ''");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string
{
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function sanitize(string $value): string
{
    return sanitizeOutput($value);
}
