<?php

require_once __DIR__ . '/../../config/database.php';

class NotificationService
{
    public static function create(
        string $title,
        string $message,
        string $type,
        ?int $userId = null,
        ?string $refTable = null,
        ?int $refId = null
    ): void {
        $pdo = getPDO();

        if ($userId === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type, reference_table, reference_id)
                 SELECT user_id, ?, ?, ?, ?, ? FROM users WHERE role IN (\'admin\',\'officer\') AND is_active = 1'
            );
            $stmt->execute([$title, $message, $type, $refTable, $refId]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type, reference_table, reference_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $title, $message, $type, $refTable, $refId]);
        }
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL
             ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0'
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (user_id = ? OR user_id IS NULL)'
        );
        $stmt->execute([$notificationId, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0'
        );
        $stmt->execute([$userId]);
    }

    public static function notifyIncident(int $incidentId, string $category, string $severity): void
    {
        $type = stripos($category, 'fire') !== false ? 'fire' :
            (stripos($category, 'poach') !== false ? 'poaching' : 'incident');
        self::create(
            'New Incident: ' . $category,
            "A {$severity} severity {$category} incident has been reported.",
            $type,
            null,
            'incidents',
            $incidentId
        );
    }
}
