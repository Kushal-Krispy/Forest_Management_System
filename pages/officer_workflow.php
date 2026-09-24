<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Officer Workflow';
$pdo = getPDO();

$statusFilter = $_GET['status'] ?? '';
$forestFilter = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;
$forests = $pdo->query('SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name')->fetchAll();

$sql = "SELECT i.*, f.forest_name, r.full_name AS reporter_name, o.full_name AS officer_name
        FROM incidents i
        LEFT JOIN forests f ON i.forest_id = f.forest_id
        LEFT JOIN users r ON i.reported_by = r.user_id
        LEFT JOIN users o ON i.assigned_officer = o.user_id
        WHERE i.deleted_at IS NULL";
$params = [];
if ($statusFilter) {
    $sql .= ' AND i.workflow_status = ?';
    $params[] = $statusFilter;
}
if ($forestFilter > 0) {
    $sql .= ' AND i.forest_id = ?';
    $params[] = $forestFilter;
}
$sql .= ' ORDER BY i.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

$officers = $pdo->query(
    "SELECT user_id, full_name FROM users WHERE role IN ('admin','officer') AND is_active = 1"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $incidentId = (int)$_POST['incident_id'];
    $newStatus = $_POST['workflow_status'];
    $officerId = (int)$_POST['assigned_officer'] ?: null;
    $invNotes = trim($_POST['investigation_notes'] ?? '');
    $resNotes = trim($_POST['resolution_notes'] ?? '');
    $resDate = $newStatus === 'resolved' ? date('Y-m-d') : null;

    $upd = $pdo->prepare(
        'UPDATE incidents SET workflow_status=?, assigned_officer=?, investigation_notes=?, resolution_notes=?, resolution_date=COALESCE(?, resolution_date) WHERE incident_id=?'
    );
    $upd->execute([$newStatus, $officerId, $invNotes, $resNotes, $resDate, $incidentId]);

    require_once __DIR__ . '/../includes/audit_tracker.php';
    logActivity('update', 'incidents', $incidentId, "Workflow: {$newStatus}");
    setFlash('success', 'Workflow updated.');
    $redirect = url('pages/officer_workflow.php');
    if ($forestFilter) $redirect .= '?forest_id=' . $forestFilter;
    if ($statusFilter) $redirect .= ($forestFilter ? '&' : '?') . 'status=' . urlencode($statusFilter);
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$statuses = ['pending', 'verified', 'resolved'];
$statusColors = ['pending' => 'warning', 'verified' => 'primary', 'resolved' => 'success'];
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('success')): ?><div class="flash flash-success"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select">
                            <option value="">All Forests</option>
                            <?php foreach ($forests as $f): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestFilter === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filter Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <?php foreach ($incidents as $i):
            $days = $i['resolution_date'] ? (strtotime($i['resolution_date']) - strtotime($i['incident_date'])) / 86400 : null;
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h3>#<?= (int)$i['incident_id'] ?> - <?= sanitize($i['category']) ?></h3>
                <span class="badge bg-<?= $statusColors[$i['workflow_status']] ?? 'secondary' ?>"><?= sanitize($i['workflow_status']) ?></span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p><?= sanitize($i['description']) ?></p>
                        <p><small>Forest: <?= sanitize($i['forest_name'] ?? 'N/A') ?> | Severity: <?= sanitize($i['severity']) ?> | Date: <?= sanitize($i['incident_date']) ?></small></p>
                        <p><small>Reporter: <?= sanitize($i['reporter_name']) ?> | Officer: <?= sanitize($i['officer_name'] ?? 'Unassigned') ?></small></p>
                        <?php if ($days !== null): ?><p><small>Time to resolve: <?= round($days) ?> days</small></p><?php endif; ?>
                    </div>
                    <?php if ($i['image_path']): ?>
                    <div class="col-md-4"><img src="<?= sanitize($i['image_path']) ?>" class="img-fluid rounded" style="max-height:150px;"></div>
                    <?php endif; ?>
                </div>
                <form method="POST" class="row g-2 mt-2 border-top pt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="incident_id" value="<?= (int)$i['incident_id'] ?>">
                    <div class="col-md-2">
                        <select name="workflow_status" class="form-select form-select-sm">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $i['workflow_status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="assigned_officer" class="form-select form-select-sm">
                            <option value="">Assign Officer</option>
                            <?php foreach ($officers as $o): ?>
                            <option value="<?= (int)$o['user_id'] ?>" <?= $i['assigned_officer'] == $o['user_id'] ? 'selected' : '' ?>><?= sanitize($o['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="investigation_notes" class="form-control form-control-sm" placeholder="Investigation notes" value="<?= sanitize($i['investigation_notes'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="resolution_notes" class="form-control form-control-sm" placeholder="Resolution notes" value="<?= sanitize($i['resolution_notes'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Update</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
