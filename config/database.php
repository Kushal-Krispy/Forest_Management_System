<?php
/**
 * DATABASE CONNECTION - PDO only (production-ready)
 */

require_once __DIR__ . '/app.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'forest_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
            '<br>Run <a href="' . url('setup/install.php') . '">setup/install.php</a> or setup/migrate_v2.php first.');
    }

    return $pdo;
}

/** @deprecated Use getPDO() - kept for gradual migration */
function getDBConnection(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}
