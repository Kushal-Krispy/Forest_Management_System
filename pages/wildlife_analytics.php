<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Animal Population Analytics';
$pdo = getPDO();
$forests = $pdo->query(
    'SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name'
)->fetchAll();

$extraJs = [asset('js/pages/wildlife_analytics.js')];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select id="forestSelect" class="form-select">
                            <option value="">-- Choose Forest --</option>
                            <?php foreach ($forests as $f): ?>
                            <option value="<?= (int)$f['forest_id'] ?>"><?= sanitize($f['forest_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="loadAnalytics" class="btn btn-primary w-100">Load Analytics</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-3 mb-3">
            <div class="stat-card"><div class="stat-value" id="currentPop">--</div><div class="stat-label">Current Population</div></div>
            <div class="stat-card"><div class="stat-value" id="totalBirths">--</div><div class="stat-label">Total Births</div></div>
            <div class="stat-card"><div class="stat-value" id="totalDeaths">--</div><div class="stat-label">Total Deaths</div></div>
        </div>

        <div class="card" style="max-width: 600px;">
            <div class="card-header"><h2>Birth & Death in Selected Forest</h2></div>
            <div class="card-body"><canvas id="birthDeathChart" height="200"></canvas></div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
