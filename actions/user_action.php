<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/users.php'));
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int)($_POST['user_id'] ?? 0);

if ($userId < 1) {
    setFlash('error', 'Please choose a valid user.');
    header('Location: ' . url('pages/users.php'));
    exit;
}

try {
    ensureSimplifiedUserRoles();
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT user_id, username, role FROM users WHERE user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        setFlash('error', 'User not found.');
        header('Location: ' . url('pages/users.php'));
        exit;
    }

    $currentRole = normalizeRoleValue($user['role']);

    if ($action === 'promote') {
        if ($currentRole !== 'public') {
            setFlash('error', 'Only public users can be promoted to officer.');
        } else {
            $pdo->prepare("UPDATE users SET role = 'officer' WHERE user_id = ?")->execute([$userId]);
            setFlash('success', 'User #' . $userId . ' promoted to Officer.');
        }
    } elseif (in_array($action, ['demote', 'depromote'], true)) {
        if ($currentRole !== 'officer') {
            setFlash('error', 'Only officers can be demoted to public.');
        } else {
            $pdo->prepare("UPDATE users SET role = 'public' WHERE user_id = ?")->execute([$userId]);
            setFlash('success', 'User #' . $userId . ' demoted to Public.');
        }
    } else {
        setFlash('error', 'Unknown user role action.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not update role. Please confirm the database role column allows admin, officer, and public.');
}

header('Location: ' . url('pages/users.php'));
exit;
