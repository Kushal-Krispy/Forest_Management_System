<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/audit_tracker.php';

requireApiLogin();
if (!canManageAnimals()) {
    jsonResponse(['error' => 'Permission denied'], 403);
}

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $qr = trim($_GET['qr'] ?? '');
    $stmt = $pdo->prepare(
        'SELECT a.*, f.forest_name FROM animals a JOIN forests f ON a.forest_id = f.forest_id
         WHERE a.qr_code = ? AND a.deleted_at IS NULL'
    );
    $stmt->execute([$qr]);
    $animal = $stmt->fetch();
    
    if ($animal) {
        // Get recent scan notes
        $scanStmt = $pdo->prepare(
            'SELECT scan_type, scan_data, scanned_at FROM animal_qr_scans 
             WHERE animal_id = ? ORDER BY scanned_at DESC LIMIT 5'
        );
        $scanStmt->execute([$animal['animal_id']]);
        $recentScans = $scanStmt->fetchAll();
        $animal['recent_scans'] = $recentScans;
        jsonResponse(['animal' => $animal], 200);
    } else {
        jsonResponse(['error' => 'Animal not found'], 404);
    }
}

requireCsrf();
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$animalId = (int)($data['animal_id'] ?? 0);
$scanType = $data['scan_type'] ?? 'sighting';
$userId = getSessionUserId();

$stmt = $pdo->prepare('SELECT * FROM animals WHERE animal_id = ?');
$stmt->execute([$animalId]);
$animal = $stmt->fetch();
if (!$animal) {
    jsonResponse(['error' => 'Animal not found'], 404);
}

$lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : null;
$health = $data['health_status'] ?? null;
$notes = trim($data['notes'] ?? '');

$pdo->prepare(
    'INSERT INTO animal_qr_scans (animal_id, scan_type, scan_data, scanned_by, latitude, longitude)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$animalId, $scanType, $notes, $userId, $lat, $lng]);

if ($scanType === 'health' && $health) {
    $pdo->prepare('UPDATE animals SET health_status = ? WHERE animal_id = ?')->execute([$health, $animalId]);
}
if ($scanType === 'location' && $lat && $lng) {
    $pdo->prepare('UPDATE animals SET latitude = ?, longitude = ? WHERE animal_id = ?')->execute([$lat, $lng, $animalId]);
}
if (in_array($scanType, ['sighting', 'relocation'], true)) {
    $pdo->prepare(
        'INSERT INTO animal_sightings (animal_id, forest_id, latitude, longitude, health_status, notes, sighted_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $animalId, $animal['forest_id'], $lat, $lng,
        $health ?? $animal['health_status'], $notes, $userId,
    ]);
}

logActivity('qr_scan', 'animals', $animalId, "QR scan: {$scanType}");
jsonResponse(['success' => true, 'message' => 'Scan recorded successfully']);
