<?php
/**
 * ANALYTICAL REPORTS
 * Uses CURSOR stored procedure: sp_incident_category_report()
 * Shows incident counts per category + fire frequency labels
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_helpers.php';
requireLogin();

$pageTitle = 'Analytical Reports';
$conn = getDBConnection();

$forestFilter = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;
$forests = $conn->query("SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name");

$proceduresMissing = !dbStoredProceduresReady($conn);

$reportData = [];
if (!$proceduresMissing) {
    if ($forestFilter > 0) {
        $stmt = $conn->prepare(
            "SELECT category AS category_name, COUNT(*) AS incident_count,
                    CASE
                        WHEN COUNT(*) >= 10 THEN 'Very High'
                        WHEN COUNT(*) >= 5 THEN 'High'
                        WHEN COUNT(*) >= 2 THEN 'Moderate'
                        ELSE 'Low'
                    END AS fire_frequency_label
             FROM incidents WHERE status='approved' AND forest_id = ?
             GROUP BY category ORDER BY incident_count DESC"
        );
        $stmt->bind_param('i', $forestFilter);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        $stmt->close();
    } else {
        if ($conn->multi_query('CALL sp_incident_category_report()')) {
            do {
                if ($result = $conn->store_result()) {
                    while ($row = $result->fetch_assoc()) {
                        $reportData[] = $row;
                    }
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
    }
}
dbFreeMysqliResults($conn);

$maxCount = 1;
foreach ($reportData as $r) {
    if ((int)$r['incident_count'] > $maxCount) {
        $maxCount = (int)$r['incident_count'];
    }
}

$forestWhere = $forestFilter > 0 ? " AND forest_id = {$forestFilter}" : '';
$totalIncidents = $conn->query("SELECT COUNT(*) AS c FROM incidents WHERE status='approved'{$forestWhere}")->fetch_assoc()['c'];
$fireCount = $conn->query("SELECT COUNT(*) AS c FROM incidents WHERE status='approved' AND category LIKE '%fire%'{$forestWhere}")->fetch_assoc()['c'];

$conn->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Forests</option>
                            <?php while ($f = $forests->fetch_assoc()): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestFilter === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($proceduresMissing && !$forestFilter): ?>
        <div class="flash flash-error" style="margin-bottom:1.5rem;">
            <strong>Stored procedures not installed.</strong>
            Reports need <code>sp_incident_category_report()</code>.
            Run <a href="<?= url('setup/repair_procedures.php') ?>">setup/repair_procedures.php</a>
            or import <code>database/procedures.sql</code> in phpMyAdmin (Import tab).
        </div>
        <?php else: ?>
        <div class="data-flow-box">
            <strong>Analytics:</strong> Incident category breakdown
            <?= $forestFilter ? 'for the selected forest' : 'across all forests (via stored procedure cursor)' ?>.
        </div>
        <?php endif; ?>

        <div class="grid-3" style="margin-bottom:2rem;">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-chart-bar"></i></div>
                <div><div class="stat-value"><?= (int)$totalIncidents ?></div><div class="stat-label">Total Approved Incidents</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-fire"></i></div>
                <div><div class="stat-value"><?= (int)$fireCount ?></div><div class="stat-label">Fire-Related Incidents</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-layer-group"></i></div>
                <div><div class="stat-value"><?= count($reportData) ?></div><div class="stat-label">Incident Categories</div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie"></i> Incidents by Category</h2>
            </div>
            <div class="card-body">
                <?php if ($proceduresMissing && !$forestFilter): ?>
                <div class="empty-state"><i class="fas fa-database"></i><p>Install stored procedures to load the cursor report.</p></div>
                <?php elseif (empty($reportData)): ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i><p>No incident data for analysis yet.</p></div>
                <?php else: ?>
                <?php foreach ($reportData as $row):
                    $pct = round(((int)$row['incident_count'] / $maxCount) * 100);
                ?>
                <div class="chart-bar-row">
                    <div class="chart-bar-label">
                        <span><strong><?= sanitize($row['category_name']) ?></strong>
                            <?php if (stripos($row['category_name'], 'fire') !== false): ?>
                            <span class="badge badge-vulnerable"><?= sanitize($row['fire_frequency_label']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span><?= (int)$row['incident_count'] ?> incidents</span>
                    </div>
                    <div class="chart-bar-track">
                        <div class="chart-bar-fill" style="width: <?= max($pct, 8) ?>%">
                            <?= (int)$row['incident_count'] ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
