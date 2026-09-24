<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/ExportService.php';

requireLogin();
if (!canExportData()) {
    http_response_code(403);
    die('Export not permitted.');
}

$table = $_GET['table'] ?? '';
$format = $_GET['format'] ?? 'csv';

$configs = [
    'forests' => [
        'headers' => ['ID', 'Code', 'Name', 'Location', 'Area', 'Ecosystem', 'Health', 'Fire Risk', 'Region'],
        'sql' => "SELECT forest_id, forest_code, forest_name, location, area_sq_km, ecosystem_type, health_score, fire_risk_score, region FROM forests WHERE deleted_at IS NULL",
    ],
    'animals' => [
        'headers' => ['ID', 'QR', 'Common Name', 'Species', 'Forest ID', 'Population', 'Status', 'Health'],
        'sql' => "SELECT animal_id, qr_code, common_name, species_name, forest_id, population_estimate, conservation_status, health_status FROM animals WHERE deleted_at IS NULL",
    ],
    'incidents' => [
        'headers' => ['ID', 'Forest', 'Category', 'Severity', 'Status', 'Date', 'Description'],
        'sql' => "SELECT i.incident_id, f.forest_name, i.category, i.severity, i.workflow_status, i.incident_date, LEFT(i.description,100) FROM incidents i LEFT JOIN forests f ON i.forest_id=f.forest_id WHERE i.deleted_at IS NULL",
    ],
    'users' => [
        'headers' => ['ID', 'Username', 'Email', 'Name', 'Role', 'Active', 'Created'],
        'sql' => "SELECT user_id, username, email, full_name, role, is_active, created_at FROM users WHERE deleted_at IS NULL",
        'admin_only' => true,
    ],
    'audit_log' => [
        'headers' => ['ID', 'Table', 'Record', 'Action', 'Details', 'User', 'Date'],
        'sql' => "SELECT a.log_id, a.table_name, a.record_id, a.action_type, LEFT(a.details,80), u.username, a.created_at FROM audit_log a LEFT JOIN users u ON a.performed_by=u.user_id ORDER BY a.created_at DESC LIMIT 500",
    ],
    'activity_logs' => [
        'headers' => ['ID', 'User', 'Action', 'Table', 'Record', 'Details', 'IP', 'Date'],
        'sql' => "SELECT a.log_id, u.username, a.action_type, a.table_name, a.record_id, LEFT(a.details,80), a.ip_address, a.created_at FROM activity_logs a LEFT JOIN users u ON a.user_id=u.user_id ORDER BY a.created_at DESC LIMIT 500",
    ],
];

if (!isset($configs[$table])) {
    http_response_code(400);
    die('Invalid table.');
}

$config = $configs[$table];
if (!empty($config['admin_only']) && !isAdmin()) {
    http_response_code(403);
    die('Admin only.');
}

$pdo = getPDO();
$rows = $pdo->query($config['sql'])->fetchAll(PDO::FETCH_NUM);
$filename = "fms_{$table}_" . date('Y-m-d_His');

switch ($format) {
    case 'excel':
        ExportService::exportExcel($filename . '.xls', $config['headers'], $rows);
    case 'pdf':
        ExportService::exportPdf("FMS Export: " . ucfirst($table), $config['headers'], $rows);
    default:
        ExportService::exportCsv($filename . '.csv', $config['headers'], $rows);
}
