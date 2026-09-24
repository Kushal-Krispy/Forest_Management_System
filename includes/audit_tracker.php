<?php
/**
 * Enhanced activity/audit tracking beyond DB triggers.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

function logActivity(
    string $action,
    ?string $tableName = null,
    ?int $recordId = null,
    ?string $details = null,
    ?int $userId = null
): void {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action_type, table_name, record_id, details, ip_address, device_info)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId ?? ($_SESSION['user_id'] ?? null),
        $action,
        $tableName,
        $recordId,
        $details,
        getClientIp(),
        getDeviceInfo(),
    ]);
}
