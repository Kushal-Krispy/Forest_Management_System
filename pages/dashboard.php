<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Analytics Dashboard';
$pdo = getPDO();

$stats = [
    'forests'  => (int)$pdo->query('SELECT COUNT(*) FROM forests WHERE deleted_at IS NULL')->fetchColumn(),
    'animals'  => (int)$pdo->query('SELECT COUNT(*) FROM animals WHERE deleted_at IS NULL')->fetchColumn(),
    'incidents'=> (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status='approved'")->fetchColumn(),
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM pending_incidents WHERE status='pending'")->fetchColumn(),
    'critical' => (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE severity='critical' AND workflow_status != 'closed'")->fetchColumn(),
];

$recentIncidents = $pdo->query(
    "SELECT i.incident_id, i.category, i.severity, i.incident_date, i.description, f.forest_name
     FROM incidents i LEFT JOIN forests f ON i.forest_id = f.forest_id
     WHERE i.status = 'approved' ORDER BY i.created_at DESC LIMIT 8"
)->fetchAll();

$categoryStats = $pdo->query(
    "SELECT category, COUNT(*) AS cnt FROM incidents WHERE status='approved' GROUP BY category ORDER BY cnt DESC"
)->fetchAll();

$extraJs = [asset('js/pages/dashboard.js')];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('success')): ?><div class="flash flash-success"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="grid-4 mb-3">
            <div class="stat-card"><div class="stat-icon green"><i class="fas fa-mountain-sun"></i></div>
                <div><div class="stat-value"><?= $stats['forests'] ?></div><div class="stat-label">Forests</div></div></div>
            <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-paw"></i></div>
                <div><div class="stat-value"><?= $stats['animals'] ?></div><div class="stat-label">Wildlife</div></div></div>
            <div class="stat-card"><div class="stat-value"><?= $stats['incidents'] ?></div><div class="stat-label">Incidents</div></div>
            <?php if (canManageIncidents()): ?>
            <div class="stat-card"><div class="stat-value"><?= $stats['pending'] ?></div><div class="stat-label">Pending Reports</div></div>
            <?php else: ?>
            <div class="stat-card"><div class="stat-value"><?= $stats['critical'] ?></div><div class="stat-label">Critical Open</div></div>
            <?php endif; ?>
        </div>

        <?php if (canManageIncidents()): ?>
        <div class="grid-4 mb-3">
            <div class="stat-card"><div class="stat-value"><?= $stats['critical'] ?></div><div class="stat-label">Critical Open</div></div>
        </div>
        <?php endif; ?>

        <div class="card mb-3" style="max-width: 600px;">
            <div class="card-header"><h2>Incident Overview</h2></div>
            <div class="card-body"><canvas id="incidentChart" height="180"></canvas></div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Recent Incidents</h2><a href="<?= url('pages/incidents.php') ?>" class="btn btn-secondary btn-sm">View All</a></div>
            <div class="card-body table-wrap">
                <table class="data-table datatable">
                    <thead><tr><th>ID</th><th>Category</th><th>Severity</th><th>Forest</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentIncidents as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['incident_id'] ?></td>
                        <td><?= sanitize($row['category']) ?></td>
                        <td><span class="badge-severity-<?= sanitize($row['severity'] ?? 'medium') ?>"><?= sanitize($row['severity'] ?? 'medium') ?></span></td>
                        <td><?= sanitize($row['forest_name'] ?? 'N/A') ?></td>
                        <td><?= sanitize($row['incident_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<script>
window.DASHBOARD_DATA = {
    categories: <?= json_encode(array_column($categoryStats, 'category')) ?>,
    counts: <?= json_encode(array_map('intval', array_column($categoryStats, 'cnt'))) ?>,
};
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
