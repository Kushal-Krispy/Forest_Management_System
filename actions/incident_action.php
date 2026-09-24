<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/db_helpers.php';
require_once __DIR__ . '/../includes/audit_tracker.php';
require_once __DIR__ . '/../includes/services/NotificationService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getPDO();

function getForestCoords(PDO $pdo, int $forestId): array
{
    $stmt = $pdo->prepare('SELECT latitude, longitude FROM forests WHERE forest_id = ?');
    $stmt->execute([$forestId]);
    $f = $stmt->fetch();
    return [$f['latitude'] ?? null, $f['longitude'] ?? null];
}

function mapSeverity(string $category): string
{
    $c = strtolower($category);
    if (str_contains($c, 'fire')) return 'high';
    if (str_contains($c, 'poach')) return 'critical';
    if (str_contains($c, 'death')) return 'high';
    if (str_contains($c, 'log')) return 'medium';
    return 'medium';
}

if ($action === 'add_official') {
    requireRole(['admin', 'officer']);
    $userId = getSessionUserId();
    $forestId = (int)$_POST['forest_id'];
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['incident_date'] ?? date('Y-m-d');
    $severity = $_POST['severity'] ?? mapSeverity($category);

    if ($date > date('Y-m-d') || $description === '' || $forestId < 1) {
        setFlash('error', 'Invalid incident data.');
        header('Location: ' . url('pages/add_incident.php'));
        exit;
    }

    $imagePath = null;
    if (!empty($_FILES['incident_image']['name'])) {
        $imagePath = uploadFile($_FILES['incident_image'], 'incidents');
    }
    [$lat, $lng] = getForestCoords($pdo, $forestId);

    $stmt = $pdo->prepare(
        "INSERT INTO incidents (forest_id, category, severity, description, image_path, incident_date,
         reported_by, approved_by, status, source, workflow_status, latitude, longitude)
         VALUES (?,?,?,?,?,?,?,?,'approved','officer','verified',?,?)"
    );
    $stmt->execute([$forestId, $category, $severity, $description, $imagePath, $date, $userId, $userId, $lat, $lng]);
    $incidentId = (int)$pdo->lastInsertId();

    NotificationService::notifyIncident($incidentId, $category, $severity);
    logActivity('create', 'incidents', $incidentId, "Official incident: {$category}");
    setFlash('success', 'Incident recorded successfully.');
    header('Location: ' . url('pages/incidents.php'));
}

elseif ($action === 'public_report') {
    requireRole(['public']);
    $userId = getSessionUserId();
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $forestId = !empty($_POST['forest_id']) ? (int)$_POST['forest_id'] : null;
    $severity = $_POST['severity'] ?? mapSeverity($category);

    if (empty($_FILES['incident_image']['name']) || $description === '') {
        setFlash('error', 'Photo and description are required.');
        header('Location: ' . url('pages/report_incident.php'));
        exit;
    }
    $imagePath = uploadFile($_FILES['incident_image'], 'incidents');
    if (!$imagePath) {
        setFlash('error', 'Invalid file upload.');
        header('Location: ' . url('pages/report_incident.php'));
        exit;
    }

    $lat = $lng = null;
    if ($forestId) {
        [$lat, $lng] = getForestCoords($pdo, $forestId);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO pending_incidents (forest_id, category, severity, description, image_path, reported_by, latitude, longitude)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([$forestId, $category, $severity, $description, $imagePath, $userId, $lat, $lng]);

    NotificationService::create('New Public Report', "A public {$category} report was submitted.", 'incident', null, 'pending_incidents', (int)$pdo->lastInsertId());
    logActivity('create', 'pending_incidents', (int)$pdo->lastInsertId(), 'Public report');
    setFlash('success', 'Report submitted! An officer will review it.');
    header('Location: ' . url('pages/report_incident.php'));
}

elseif ($action === 'approve') {
    requireRole(['admin', 'officer']);
    $userId = getSessionUserId();
    if (!dbStoredProceduresReady(getDBConnection())) {
        setFlash('error', 'Stored procedure missing. Run setup/migrate_v2.php.');
        header('Location: ' . url('pages/pending_reports.php'));
        exit;
    }
    $pendingId = (int)$_POST['pending_id'];
    $forestId = (int)$_POST['forest_id'];
    $editDescription = trim($_POST['edit_description'] ?? '');
    $conn = getDBConnection();
    $stmt = $conn->prepare('CALL sp_approve_pending_incident(?, ?, ?)');
    $stmt->bind_param('iii', $pendingId, $userId, $forestId);
    $stmt->execute();
    $stmt->close();
    dbFreeMysqliResults($conn);

    $pdo->prepare("UPDATE incidents SET severity = (SELECT severity FROM pending_incidents WHERE pending_id = ?), workflow_status = 'under_review', latitude = (SELECT latitude FROM forests WHERE forest_id = ?), longitude = (SELECT longitude FROM forests WHERE forest_id = ?) WHERE incident_id = (SELECT MAX(incident_id) FROM incidents)")->execute([$pendingId, $forestId, $forestId]);

    // Update description if provided
    if ($editDescription !== '') {
        $pdo->prepare("UPDATE incidents SET description = ? WHERE incident_id = (SELECT MAX(incident_id) FROM incidents)")->execute([$editDescription]);
    }

    // Notify the reporter
    $stmt = $pdo->prepare("SELECT reported_by FROM pending_incidents WHERE pending_id=?");
    $stmt->execute([$pendingId]);
    $reporterId = $stmt->fetchColumn();
    if ($reporterId) {
        NotificationService::create('Report Approved', 'Your incident report was approved and is now being reviewed.', 'incident', $reporterId, 'incidents', $pendingId);
    }
    logActivity('approve', 'pending_incidents', $pendingId, 'Approved public report');
    setFlash('success', 'Report approved and moved to incidents.');
    header('Location: ' . url('pages/pending_reports.php'));
    exit;
}

elseif ($action === 'reject') {
    requireRole(['admin', 'officer']);
    $userId = getSessionUserId();
    $pendingId = (int)$_POST['pending_id'];
    $notes = trim($_POST['review_notes'] ?? '');
    $pdo->prepare("UPDATE pending_incidents SET status='rejected', reviewed_by=?, review_notes=?, reviewed_at=NOW(), workflow_status='closed' WHERE pending_id=?")
        ->execute([$userId, $notes, $pendingId]);
    
    // Notify the reporter
    $stmt = $pdo->prepare("SELECT reported_by FROM pending_incidents WHERE pending_id=?");
    $stmt->execute([$pendingId]);
    $reporterId = $stmt->fetchColumn();
    if ($reporterId) {
        NotificationService::create('Report Rejected', "Your incident report was rejected. Reason: " . ($notes ?: 'No reason provided'), 'incident', $reporterId, 'pending_incidents', $pendingId);
    }
    
    logActivity('reject', 'pending_incidents', $pendingId, 'Rejected public report');
    setFlash('success', 'Report rejected.');
    header('Location: ' . url('pages/pending_reports.php'));
}

elseif ($action === 'delete') {
    requireRole(['admin', 'officer']);
    $incidentId = (int)$_POST['incident_id'];
    if ($incidentId > 0) {
        $pdo->prepare("DELETE FROM incidents  WHERE incident_id = ?")->execute([$incidentId]);
        logActivity('delete', 'incidents', $incidentId, 'Deleted incident');
        setFlash('success', 'Incident deleted successfully.');
    } else {
        setFlash('error', 'Invalid incident ID.');
    }
    header('Location: ' . url('pages/incidents.php'));
    exit;
}

elseif ($action === 'update') {
    requireRole(['admin', 'officer']);
    $incidentId = (int)$_POST['incident_id'];
    $category = trim($_POST['category'] ?? '');
    $severity = $_POST['severity'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    $forestId = (int)$_POST['forest_id'];
    $incidentDate = $_POST['incident_date'] ?? date('Y-m-d');
    
    if ($incidentId > 0 && $category !== '' && $description !== '') {
        $stmt = $pdo->prepare(
            "UPDATE incidents SET category=?, severity=?, description=?, forest_id=?, incident_date=? WHERE incident_id=?"
        );
        $stmt->execute([$category, $severity, $description, $forestId, $incidentDate, $incidentId]);
        
        // Update coordinates if forest changed
        [$lat, $lng] = getForestCoords($pdo, $forestId);
        $pdo->prepare("UPDATE incidents SET latitude=?, longitude=? WHERE incident_id=?")->execute([$lat, $lng, $incidentId]);
        
        logActivity('update', 'incidents', $incidentId, "Updated incident: {$category}");
        setFlash('success', 'Incident updated successfully.');
    } else {
        setFlash('error', 'Invalid incident data.');
    }
    header('Location: ' . url('pages/incidents.php'));
    exit;
}

exit;
