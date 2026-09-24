<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_tracker.php';

if (isLoggedIn() && !empty($_SESSION['user_id'])) {
    logActivity('logout', 'users', (int)$_SESSION['user_id'], 'User logged out');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . url('pages/login.php'));
exit;
