<?php
require_once __DIR__ . '/bootstrap.php';

requireApiLogin();
$pdo = getPDO();
$type = $_GET['type'] ?? 'all';
$forestId = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : null;
$search = trim($_GET['search'] ?? '');

$data = ['forests' => [], 'wildlife' => [], 'incidents' => []];

$sql = "SELECT f.forest_id, f.forest_name, f.forest_code, f.latitude, f.longitude,
        f.area_sq_km, f.ecosystem_type, f.health_score, f.fire_risk_score, f.region,
        (SELECT COALESCE(SUM(population_estimate),0) FROM animals WHERE forest_id = f.forest_id AND deleted_at IS NULL) AS animal_population,
        (SELECT COUNT(*) FROM incidents WHERE forest_id = f.forest_id AND status='approved' AND deleted_at IS NULL) AS incident_count
        FROM forests f WHERE f.deleted_at IS NULL AND f.latitude IS NOT NULL AND f.longitude IS NOT NULL";
$params = [];

if ($search !== '') {
    $sql .= " AND (f.forest_name LIKE ? OR f.forest_code LIKE ? OR f.region LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($forestId) {
    $sql .= " AND f.forest_id = ?";
    $params[] = $forestId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data['forests'] = $stmt->fetchAll();
$matchedForestIds = array_map(static fn($forest) => (int)$forest['forest_id'], $data['forests']);

$applyForestScope = static function (string &$sql, array &$params, string $column) use ($forestId, $search, $matchedForestIds): bool {
    if ($forestId) {
        $sql .= " AND {$column} = ?";
        $params[] = $forestId;
        return true;
    }

    if ($search !== '') {
        if (!$matchedForestIds) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($matchedForestIds), '?'));
        $sql .= " AND {$column} IN ({$placeholders})";
        $params = array_merge($params, $matchedForestIds);
    }

    return true;
};

if ($type === 'all' || $type === 'wildlife') {
    $sql = "SELECT a.animal_id, a.common_name, a.species_name, a.health_status, a.latitude, a.longitude,
                a.forest_id, f.forest_name
         FROM animals a JOIN forests f ON a.forest_id = f.forest_id
         WHERE a.deleted_at IS NULL AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL";
    $params = [];
    if ($applyForestScope($sql, $params, 'a.forest_id')) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data['wildlife'] = $stmt->fetchAll();
    }
}

if ($type === 'all' || $type === 'incidents') {
    $sql = "SELECT i.incident_id, i.category, i.severity, i.latitude, i.longitude, i.incident_date,
            i.workflow_status, f.forest_name, f.forest_id
            FROM incidents i LEFT JOIN forests f ON i.forest_id = f.forest_id
            WHERE i.status = 'approved' AND i.deleted_at IS NULL AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL";
    $params = [];

    if (!empty($_GET['incident_type'])) {
        $sql .= " AND i.category LIKE ?";
        $params[] = '%' . $_GET['incident_type'] . '%';
    }
    if (!empty($_GET['severity'])) {
        $sql .= " AND i.severity = ?";
        $params[] = $_GET['severity'];
    }
    if ($applyForestScope($sql, $params, 'i.forest_id')) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data['incidents'] = $stmt->fetchAll();
    }
}

jsonResponse($data);
