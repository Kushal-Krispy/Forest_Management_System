<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_tracker.php';

$action = $_POST['action'] ?? 'save';

if ($action === 'delete') {
    requireRole(['admin', 'officer']);
    requireCsrf();
    header('Content-Type: application/json; charset=utf-8');
    $forestId = (int)($_POST['forest_id'] ?? 0);
    if ($forestId > 0) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT forest_name FROM forests WHERE forest_id = ?');
        $stmt->execute([$forestId]);
        $forest = $stmt->fetch();
        if ($forest) {
            $pdo->prepare('UPDATE forests SET deleted_at = NOW() WHERE forest_id = ?')->execute([$forestId]);
            logActivity('delete', 'forests', $forestId, "Forest deleted: {$forest['forest_name']}");
            echo json_encode(['success' => true]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

requireRole(['admin', 'officer']);
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/forest_info.php'));
    exit;
}

$forestId = (int)($_POST['forest_id'] ?? 0);
$name = trim($_POST['forest_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$area = (float)($_POST['area_sq_km'] ?? 0);
$ecosystem = trim($_POST['ecosystem_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$region = trim($_POST['region'] ?? '');
$lat = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
$lng = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
$forestCode = trim($_POST['forest_code'] ?? '');
$year = (isset($_POST['established_year']) && $_POST['established_year'] !== '') ? (int)$_POST['established_year'] : null;
$userId = getSessionUserId();

if ($name === '' || $location === '' || $area <= 0 || $ecosystem === '') {
    setFlash('error', 'Please fill in all required forest fields.');
    header('Location: ' . url('pages/forest_info.php'));
    exit;
}

if ($lat !== null && $lng !== null && !isValidCoordinates($lat, $lng)) {
    setFlash('error', 'Invalid coordinates.');
    header('Location: ' . url('pages/forest_info.php'));
    exit;
}

$pdo = getPDO();

try {
    if ($forestId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE forests SET forest_name=?, forest_code=?, location=?, latitude=?, longitude=?,
             area_sq_km=?, ecosystem_type=?, region=?, description=?, established_year=? WHERE forest_id=?'
        );
        $stmt->execute([$name, $forestCode ?: null, $location, $lat, $lng, $area, $ecosystem, $region, $description, $year, $forestId]);
        logActivity('update', 'forests', $forestId, "Forest updated: {$name}");
        setFlash('success', 'Forest updated successfully.');
    } else {
        if ($forestCode === '') {
            $forestCode = 'FR-' . strtoupper(bin2hex(random_bytes(2)));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO forests (forest_code, forest_name, location, latitude, longitude, area_sq_km,
             ecosystem_type, region, description, established_year, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$forestCode, $name, $location, $lat, $lng, $area, $ecosystem, $region, $description, $year, $userId]);
        logActivity('create', 'forests', (int)$pdo->lastInsertId(), "Forest added: {$name}");
        setFlash('success', 'Forest added successfully.');
    }
} catch (PDOException $e) {
    setFlash('error', 'Could not save forest. Check forest code is unique.');
}

header('Location: ' . url('pages/forest_info.php'));
exit;
