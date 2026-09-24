<?php
/**
 * Database helpers: procedure checks and SQL file import via mysql CLI.
 */

function dbProcedureExists(mysqli $conn, string $name): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.ROUTINES
         WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?'
    );
    $type = 'PROCEDURE';
    $stmt->bind_param('ss', $name, $type);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function dbStoredProceduresReady(mysqli $conn): bool
{
    return dbProcedureExists($conn, 'sp_incident_category_report')
        && dbProcedureExists($conn, 'sp_approve_pending_incident');
}

/**
 * Import a .sql file using the mysql client (handles DELIMITER blocks).
 * Returns [success => bool, output => string[]]
 */
function dbImportSqlFile(string $mysqlBin, string $user, string $pass, string $sqlFile): array
{
    if (!file_exists($mysqlBin) || !is_readable($sqlFile)) {
        return ['success' => false, 'output' => ['MySQL CLI or SQL file not found.']];
    }

    $sqlFile = str_replace('\\', '/', realpath($sqlFile));
    $auth = '-u ' . escapeshellarg($user);
    if ($pass !== '') {
        $auth .= ' -p' . escapeshellarg($pass);
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'cmd /c "type ' . escapeshellarg($sqlFile) . ' | '
            . escapeshellarg($mysqlBin) . ' ' . $auth . ' 2>&1"';
    } else {
        $cmd = escapeshellarg($mysqlBin) . ' ' . $auth
            . ' < ' . escapeshellarg($sqlFile) . ' 2>&1';
    }

    exec($cmd, $output, $code);
    return ['success' => $code === 0, 'output' => $output];
}

function dbFreeMysqliResults(mysqli $conn): void
{
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}
