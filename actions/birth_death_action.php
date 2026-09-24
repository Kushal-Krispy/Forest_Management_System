<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/report_birth_death.php'));
    exit;
}

requireCsrf();
$pdo = getPDO();

$animalId = (int)($_POST['animal_id'] ?? 0);
$forestId = (int)($_POST['forest_id'] ?? 0);
$eventType = $_POST['event_type'] ?? '';
$eventCount = max(1, (int)($_POST['event_count'] ?? 1));
$causeOfDeath = trim($_POST['cause_of_death'] ?? '');

if (!$animalId || !$forestId || !in_array($eventType, ['birth', 'death'], true)) {
  setFlash('error', 'Please select a forest, animal, and event type.');
  header('Location: ' . url('pages/report_birth_death.php'));
  exit;
}
if ($eventType === 'death' && $causeOfDeath === '') {
  setFlash('error', 'Cause of death is required for death events.');
  header('Location: ' . url('pages/report_birth_death.php?forest_id=' . $forestId));
  exit;
}

$check = $pdo->prepare('SELECT animal_id FROM animals WHERE animal_id = ? AND forest_id = ? AND deleted_at IS NULL');
$check->execute([$animalId, $forestId]);
if (!$check->fetch()) {
    setFlash('error', 'Selected animal does not belong to the chosen forest.');
    header('Location: ' . url('pages/report_birth_death.php?forest_id=' . $forestId));
    exit;
}

$pdo->prepare(
    'INSERT INTO animal_birth_death_events (animal_id, forest_id, event_type, event_count, cause_of_death, recorded_by)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([
    $animalId,
    $forestId,
    $eventType,
    $eventCount,
    $eventType === 'death' ? $causeOfDeath : null,
    getSessionUserId(),
]);

$delta = $eventType === 'birth' ? $eventCount : -$eventCount;
$pdo->prepare(
    'UPDATE animals SET population_estimate = GREATEST(0, population_estimate + ?) WHERE animal_id = ?'
)->execute([$delta, $animalId]);
require_once __DIR__ . '/../includes/audit_tracker.php';
require_once __DIR__ . '/../includes/services/PopulationAnalytics.php';
PopulationAnalytics::recordSnapshot($animalId, getSessionUserId());
logActivity('create', 'animal_birth_death_events', (int)$pdo->lastInsertId(), ucfirst($eventType) . " x{$eventCount} for animal #{$animalId}");
setFlash('success', ucfirst($eventType) . " event recorded successfully ({$eventCount}).");
header('Location: ' . url('pages/report_birth_death.php?forest_id=' . $forestId));
exit;