<?php
/**
 * ONE-TIME INSTALLER
 * Open: http://localhost/dbms/setup/install.php
 * Requires XAMPP Apache + MySQL running.
 */
require_once __DIR__ . '/../includes/db_helpers.php';

$host = 'localhost';
$user = 'root';
$pass = '';
$phpBin = 'C:\\xampp\\php\\php.exe';
$mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;padding:20px;line-height:1.5;">';
echo "Forest Management System - Installer\n";
echo str_repeat('=', 50) . "\n\n";

$schemaFile = realpath(__DIR__ . '/../database/schema.sql');
$proceduresFile = realpath(__DIR__ . '/../database/procedures.sql');
$errors = [];

if (file_exists($mysqlBin) && $schemaFile) {
    $pre = escapeshellarg($mysqlBin) . ' -u ' . escapeshellarg($user);
    if ($pass !== '') {
        $pre .= ' -p' . escapeshellarg($pass);
    }
    exec($pre . ' -e "DROP DATABASE IF EXISTS forest_management;" 2>&1', $outDrop, $cDrop);

    $result = dbImportSqlFile($mysqlBin, $user, $pass, $schemaFile);
    if ($result['success']) {
        echo "Imported schema.sql successfully.\n\n";
    } else {
        $errors = $result['output'];
        echo "mysql import reported an error:\n" . implode("\n", $errors) . "\n\n";
    }
} else {
    echo "MySQL CLI not found at: $mysqlBin\n";
    echo "Install XAMPP or import database/schema.sql manually in phpMyAdmin.\n\n";
}

$conn = @new mysqli($host, $user, $pass, 'forest_management');

if ($conn->connect_error) {
    echo "Database 'forest_management' is not available.\n";
    echo "Fix: Start MySQL in XAMPP, then refresh this page.\n";
    echo "Or import database/schema.sql in phpMyAdmin (Import tab).\n</pre>";
    exit;
}

if (!dbStoredProceduresReady($conn) && file_exists($mysqlBin) && $proceduresFile) {
    echo "Stored procedures missing — installing from procedures.sql...\n";
    $procResult = dbImportSqlFile($mysqlBin, $user, $pass, $proceduresFile);
    if ($procResult['success']) {
        echo "Installed stored procedures successfully.\n\n";
    } else {
        echo "Procedure repair failed:\n" . implode("\n", $procResult['output']) . "\n";
        echo "Manual fix: phpMyAdmin → Import → database/procedures.sql\n\n";
    }
}

if (dbStoredProceduresReady($conn)) {
    echo "Verified: stored procedures installed.\n\n";
} else {
    echo "WARNING: Stored procedures still missing.\n";
    echo "Fix: open setup/repair_procedures.php or import database/procedures.sql\n\n";
}

// Set demo passwords
if (file_exists($phpBin)) {
    $hash = trim(shell_exec('"' . $phpBin . '" -r "echo password_hash(\'password123\', PASSWORD_DEFAULT);"'));
} else {
    $hash = '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW';
}

$stmt = $conn->prepare('UPDATE users SET password_hash = ?');
$stmt->bind_param('s', $hash);
$stmt->execute();
$conn->close();

echo "Demo passwords: password123\n\n";
echo "Open: http://localhost/dbms/\n\n";
echo "Accounts: admin | officer1 | public1\n\n";
echo "SECURITY: Delete the setup/ folder after installation.\n";
echo '</pre>';
