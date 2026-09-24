<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Report Birth & Death';
$pdo = getPDO();

$forests = $pdo->query('SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name')->fetchAll();
$forestId = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;

$animals = [];
if ($forestId > 0) {
    $stmt = $pdo->prepare(
        'SELECT animal_id, common_name, species_name, population_estimate
         FROM animals WHERE forest_id = ? AND deleted_at IS NULL ORDER BY common_name'
    );
    $stmt->execute([$forestId]);
    $animals = $stmt->fetchAll();
}

$recentEvents = [];
if ($forestId > 0) {
    $stmt = $pdo->prepare(
        'SELECT e.*, a.common_name, u.full_name AS recorded_by_name
         FROM animal_birth_death_events e
         JOIN animals a ON e.animal_id = a.animal_id
         LEFT JOIN users u ON e.recorded_by = u.user_id
         WHERE e.forest_id = ?
         ORDER BY e.recorded_at DESC LIMIT 50'
    );
    $stmt->execute([$forestId]);
    $recentEvents = $stmt->fetchAll();
}

$extraJs = [asset('js/pages/report_birth_death.js')];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('success')): ?><div class="flash flash-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <?php if ($msg = getFlash('error')): ?><div class="flash flash-error"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><h2><i class="fas fa-notes-medical"></i> Report Birth & Death</h2></div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Choose Forest --</option>
                            <?php foreach ($forests as $f): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestId === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($forestId > 0): ?>
                <form method="POST" action="<?= url('actions/birth_death_action.php') ?>" class="row g-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="forest_id" value="<?= $forestId ?>">
                    <div class="col-md-4">
                        <label class="form-label">Select Animal</label>
                        <select name="animal_id" id="animalSelect" class="form-select" required>
                            <option value="">-- Choose Animal --</option>
                            <?php foreach ($animals as $a): ?>
                            <option value="<?= (int)$a['animal_id'] ?>">
                                <?= sanitize($a['common_name']) ?> (Pop: <?= (int)$a['population_estimate'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Event Type</label>
                        <select name="event_type" id="eventType" class="form-select" required>
                            <option value="birth">Birth</option>
                            <option value="death">Death</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Count</label>
                        <input type="number" name="event_count" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-4" id="causeField" style="display:none;">
                        <label class="form-label">Cause of Death</label>
                        <input type="text" name="cause_of_death" id="causeInput" class="form-control" placeholder="e.g. Natural causes, Poaching, Disease">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Record Event</button>
                    </div>
                </form>
                <?php else: ?>
                <p class="text-muted">Select a forest to view animals and record birth or death events.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($forestId > 0 && !empty($recentEvents)): ?>
        <div class="card">
            <div class="card-header"><h2>Recent Events</h2></div>
            <div class="card-body table-wrap">
                <table class="data-table datatable">
                    <thead>
                        <tr><th>Animal</th><th>Type</th><th>Count</th><th>Cause of Death</th><th>Recorded By</th><th>Date & Time</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentEvents as $e): ?>
                    <tr>
                        <td><?= sanitize($e['common_name']) ?></td>
                        <td><span class="badge bg-<?= $e['event_type'] === 'birth' ? 'success' : 'danger' ?>"><?= sanitize($e['event_type']) ?></span></td>
                        <td><?= (int)$e['event_count'] ?></td>
                        <td><?= sanitize($e['cause_of_death'] ?? '—') ?></td>
                        <td><?= sanitize($e['recorded_by_name'] ?? 'System') ?></td>
                        <td><?= sanitize($e['recorded_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
