<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/login.php'));
    exit;
}

requireCsrf();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    setFlash('error', 'Please enter username and password.');
    header('Location: ' . url('pages/login.php'));
    exit;
}

$pdo = getPDO();
ensureSimplifiedUserRoles();
$stmt = $pdo->prepare('SELECT user_id, username, password_hash, role, full_name FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL');
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = normalizeRoleValue($user['role']);
    $_SESSION['full_name'] = $user['full_name'];

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
    logActivity('login', 'users', (int)$user['user_id'], 'User logged in');

    setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
    header('Location: ' . url('pages/dashboard.php'));
} else {
    logActivity('login_failed', 'users', null, 'Failed login: ' . $username);
    setFlash('error', 'Invalid username or password.');
    header('Location: ' . url('pages/login.php'));
}
exit;
