<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';

requireApiLogin();
$userId = getSessionUserId();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'count':
        jsonResponse(['count' => NotificationService::unreadCount($userId)]);

    case 'list':
        jsonResponse(['notifications' => NotificationService::forUser($userId, 30)]);

    case 'mark_read':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        NotificationService::markRead($id, $userId);
        jsonResponse(['success' => true]);

    case 'mark_all_read':
        NotificationService::markAllRead($userId);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
