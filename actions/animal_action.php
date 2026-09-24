<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/audit_tracker.php';
require_once __DIR__ . '/../includes/services/PopulationAnalytics.php';

requireRole(['admin', 'officer']);
requireCsrf();

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $animalId = (int)($_POST['animal_id'] ?? 0);
    if ($animalId > 0) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('DELETE FROM animals  WHERE animal_id = ?');
        $stmt->execute([$animalId]);
        logActivity('delete', 'animals', $animalId, 'Deleted animal');
        setFlash('success', 'Animal deleted successfully.');
    } else {
        setFlash('error', 'Invalid animal ID.');
    }
    header('Location: ' . url('pages/animals.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/update_animal.php'));
    exit;
}

$animalId   = (int)($_POST['animal_id'] ?? 0);
$forestId   = (int)($_POST['forest_id'] ?? 0);
$commonName = trim($_POST['common_name'] ?? '');
$species    = trim($_POST['species_name'] ?? '');
$population = max(0, (int)($_POST['population_estimate'] ?? 0));
$status     = $_POST['conservation_status'] ?? 'Least Concern';
$health     = $_POST['health_status'] ?? 'healthy';
$notes      = trim($_POST['notes'] ?? '');
$userId     = getSessionUserId();

if ($commonName === '' || $species === '' || $forestId < 1) {
    setFlash('error', 'Species, common name, and forest are required.');
    header('Location: ' . url('pages/update_animal.php'));
    exit;
}

$imagePath = null;
if (!empty($_FILES['animal_image']['name'])) {
    $imagePath = uploadFile($_FILES['animal_image'], 'animals');
    if (!$imagePath) {
        setFlash('error', 'Invalid image. Use JPG, PNG or PDF under 5MB.');
        header('Location: ' . url('pages/update_animal.php' . ($animalId ? "?edit=$animalId" : '')));
        exit;
    }
}

$pdo = getPDO();

if ($animalId > 0) {
    if ($imagePath) {
        $stmt = $pdo->prepare(
            'UPDATE animals SET forest_id=?, species_name=?, common_name=?, population_estimate=?,
             conservation_status=?, health_status=?, notes=?, image_path=?, added_by=? WHERE animal_id=?'
        );
        $stmt->execute([$forestId, $species, $commonName, $population, $status, $health, $notes, $imagePath, $userId, $animalId]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE animals SET forest_id=?, species_name=?, common_name=?, population_estimate=?,
             conservation_status=?, health_status=?, notes=?, added_by=? WHERE animal_id=?'
        );
        $stmt->execute([$forestId, $species, $commonName, $population, $status, $health, $notes, $userId, $animalId]);
    }
    PopulationAnalytics::recordSnapshot($animalId, $userId);
    logActivity('update', 'animals', $animalId, "Updated: {$commonName}");
    setFlash('success', 'Animal updated successfully.');
} else {
    $qrCode = 'FMS-ANM-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare(
        'INSERT INTO animals (qr_code, forest_id, species_name, common_name, population_estimate,
         conservation_status, health_status, notes, image_path, added_by) VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$qrCode, $forestId, $species, $commonName, $population, $status, $health, $notes, $imagePath, $userId]);
    $newId = (int)$pdo->lastInsertId();
    PopulationAnalytics::recordSnapshot($newId, $userId);
    logActivity('create', 'animals', $newId, "Added: {$commonName}");
    setFlash('success', 'Animal added with QR code: ' . $qrCode);
}

header('Location: ' . url('pages/animals.php'));
exit;
