<?php
/**
 * FMS 2.0 Migration - upgrades existing database without data loss.
 * Open: http://localhost/dbms-demo/setup/migrate_v2.php
 */
require_once __DIR__ . '/../includes/db_helpers.php';

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;padding:20px;line-height:1.5;">';
echo "Forest Management System 2.0 - Migration\n";
echo str_repeat('=', 50) . "\n\n";

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'forest_management';
$mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';

$conn = @new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("MySQL connection failed.\n</pre>");
}

$conn->query("CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbName);

$tables = $conn->query("SHOW TABLES");
$hasBase = $tables && $tables->num_rows > 0;

if (!$hasBase) {
    echo "No existing tables found. Running full schema install...\n";
    $schemaFile = realpath(__DIR__ . '/../database/schema.sql');
    if (file_exists($mysqlBin) && $schemaFile) {
        dbImportSqlFile($mysqlBin, $user, $pass, $schemaFile);
        echo "Schema installed.\n\n";
    } else {
        echo "Import database/schema.sql manually in phpMyAdmin.\n</pre>";
        exit;
    }
    $conn->select_db($dbName);
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $r && $r->num_rows > 0;
}

function tableExists(mysqli $conn, string $table): bool
{
    $r = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $r && $r->num_rows > 0;
}

function addColumn(mysqli $conn, string $table, string $sql): void
{
    if ($conn->query($sql)) {
        echo "  + {$table}: column added\n";
    } else {
        echo "  ! {$table}: " . $conn->error . "\n";
    }
}

echo "Migrating tables...\n";

if (tableExists($conn, 'users')) {
    if (!columnExists($conn, 'users', 'last_login')) {
        addColumn($conn, 'users', 'ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL');
    }
    if (!columnExists($conn, 'users', 'deleted_at')) {
        addColumn($conn, 'users', 'ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL');
    }
}

$forestCols = [
    'forest_code' => "VARCHAR(20) NULL",
    'latitude' => "DECIMAL(10,8) NULL",
    'longitude' => "DECIMAL(11,8) NULL",
    'region' => "VARCHAR(100) NULL",
    'boundary_geojson' => "TEXT NULL",
    'vegetation_density' => "DECIMAL(5,2) DEFAULT 50.00",
    'health_score' => "DECIMAL(5,2) DEFAULT 0.00",
    'fire_risk_score' => "DECIMAL(5,2) DEFAULT 0.00",
    'deleted_at' => "TIMESTAMP NULL DEFAULT NULL",
];
if (tableExists($conn, 'forests')) {
    foreach ($forestCols as $col => $def) {
        if (!columnExists($conn, 'forests', $col)) {
            addColumn($conn, 'forests', "ALTER TABLE forests ADD COLUMN {$col} {$def}");
        }
    }
    $conn->query("UPDATE forests SET forest_code = CONCAT('FR-', LPAD(forest_id, 4, '0')) WHERE forest_code IS NULL");
    $conn->query("UPDATE forests SET latitude = 45.5231, longitude = -122.6765 WHERE forest_id = 1 AND latitude IS NULL");
    $conn->query("UPDATE forests SET latitude = 40.7128, longitude = -74.0060 WHERE forest_id = 2 AND latitude IS NULL");
}

$animalCols = [
    'qr_code' => "VARCHAR(64) NULL",
    'health_status' => "ENUM('healthy','injured','sick','critical','deceased') DEFAULT 'healthy'",
    'latitude' => "DECIMAL(10,8) NULL",
    'longitude' => "DECIMAL(11,8) NULL",
    'deleted_at' => "TIMESTAMP NULL DEFAULT NULL",
];
if (tableExists($conn, 'animals')) {
    foreach ($animalCols as $col => $def) {
        if (!columnExists($conn, 'animals', $col)) {
            addColumn($conn, 'animals', "ALTER TABLE animals ADD COLUMN {$col} {$def}");
        }
    }
    $conn->query("UPDATE animals SET qr_code = CONCAT('FMS-ANM-', LPAD(animal_id, 5, '0')) WHERE qr_code IS NULL");
}

$incidentCols = [
    'severity' => "ENUM('low','medium','high','critical') DEFAULT 'medium'",
    'workflow_status' => "ENUM('pending','under_review','verified','resolved','closed') DEFAULT 'verified'",
    'assigned_officer' => "INT NULL",
    'investigation_notes' => "TEXT NULL",
    'resolution_notes' => "TEXT NULL",
    'resolution_date' => "DATE NULL",
    'latitude' => "DECIMAL(10,8) NULL",
    'longitude' => "DECIMAL(11,8) NULL",
    'deleted_at' => "TIMESTAMP NULL DEFAULT NULL",
];
if (tableExists($conn, 'incidents')) {
    foreach ($incidentCols as $col => $def) {
        if (!columnExists($conn, 'incidents', $col)) {
            addColumn($conn, 'incidents', "ALTER TABLE incidents ADD COLUMN {$col} {$def}");
        }
    }
}

$migrationFile = realpath(__DIR__ . '/../database/migration_v2.sql');
if (file_exists($mysqlBin) && $migrationFile) {
    echo "\nRunning migration_v2.sql (new tables + seed)...\n";
    $result = dbImportSqlFile($mysqlBin, $user, $pass, $migrationFile);
    if ($result['success']) {
        echo "Migration SQL completed.\n";
    } else {
        echo "Some migration statements may have been skipped (already applied).\n";
    }
}

$birthDeathFile = realpath(__DIR__ . '/../database/migration_birth_death.sql');
if (file_exists($mysqlBin) && $birthDeathFile) {
    echo "Running migration_birth_death.sql...\n";
    dbImportSqlFile($mysqlBin, $user, $pass, $birthDeathFile);
}

if (!dbStoredProceduresReady($conn)) {
    $procFile = realpath(__DIR__ . '/../database/procedures.sql');
    if ($procFile && file_exists($mysqlBin)) {
        dbImportSqlFile($mysqlBin, $user, $pass, $procFile);
        echo "Procedures installed.\n";
    }
}

$phpBin = 'C:\\xampp\\php\\php.exe';
if (file_exists($phpBin)) {
    $hash = trim(shell_exec('"' . $phpBin . '" -r "echo password_hash(\'password123\', PASSWORD_DEFAULT);"'));
    $conn->query("UPDATE users SET password_hash = '" . $conn->real_escape_string($hash) . "'");
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Migration complete!\n";
echo "Open: http://localhost/dbms-demo/\n";
echo "Demo accounts: admin | officer1 | wildlife1 | analyst1 | public1\n";
echo "Password: password123\n";
echo "\nSECURITY: Delete setup/ folder after migration.\n";
echo '</pre>';
