<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/register.php'));
    exit;
}

requireCsrf();

$fullName = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if ($password !== $confirm) {
    setFlash('error', 'Passwords do not match.');
    header('Location: ' . url('pages/register.php'));
    exit;
}

$pwErrors = validatePassword($password);
if ($pwErrors) {
    setFlash('error', implode(' ', $pwErrors));
    header('Location: ' . url('pages/register.php'));
    exit;
}

$pdo = getPDO();
ensureSimplifiedUserRoles();
$check = $pdo->prepare('SELECT user_id FROM users WHERE username = ? OR email = ?');
$check->execute([$username, $email]);
if ($check->fetch()) {
    setFlash('error', 'Username or email already exists.');
    header('Location: ' . url('pages/register.php'));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)');
if ($stmt->execute([$username, $email, $hash, $fullName, 'public'])) {
    logActivity('register', 'users', (int)$pdo->lastInsertId(), 'New public registration');
    setFlash('success', 'Registration successful! You can now login.');
    header('Location: ' . url('pages/login.php'));
} else {
    setFlash('error', 'Registration failed.');
    header('Location: ' . url('pages/register.php'));
}
exit;
