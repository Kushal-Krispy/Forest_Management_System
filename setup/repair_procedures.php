<?php
/**
 * Repair missing stored procedures without wiping the database.
 * Open: http://localhost/dbms/setup/repair_procedures.php
 */
require_once __DIR__ . '/../includes/db_helpers.php';

$host = 'localhost';
$user = 'root';
$pass = '';
$mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$proceduresFile = realpath(__DIR__ . '/../database/procedures.sql');

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;padding:20px;line-height:1.5;">';
echo "Repair Stored Procedures\n";
echo str_repeat('=', 40) . "\n\n";

$conn = @new mysqli($host, $user, $pass, 'forest_management');
if ($conn->connect_error) {
    echo "Cannot connect to forest_management.\n";
    echo "Run setup/install.php first or import schema.sql.\n</pre>";
    exit;
}

if (dbStoredProceduresReady($conn)) {
    echo "All procedures already installed. Nothing to do.\n</pre>";
    $conn->close();
    exit;
}

if (!file_exists($mysqlBin) || !$proceduresFile) {
    echo "Automatic repair needs XAMPP mysql.exe.\n";
    echo "Manual: phpMyAdmin → Import → database/procedures.sql\n</pre>";
    $conn->close();
    exit;
}

$result = dbImportSqlFile($mysqlBin, $user, $pass, $proceduresFile);
if ($result['success'] && dbStoredProceduresReady($conn)) {
    echo "Success: sp_incident_category_report and sp_approve_pending_incident installed.\n";
    echo "Test: CALL sp_incident_category_report();\n</pre>";
} else {
    echo "Repair failed:\n" . implode("\n", $result['output']) . "\n";
    echo "\nTry phpMyAdmin → Import tab → database/procedures.sql\n</pre>";
}

$conn->close();
