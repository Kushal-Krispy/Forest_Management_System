<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/FireRiskEngine.php';
requireLogin();

$pageTitle = 'Fire Risk Prediction';
$results = FireRiskEngine::calculateAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$riskColors = ['low' => '#22c55e', 'moderate' => '#eab308', 'high' => '#f97316', 'critical' => '#ef4444'];
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h2><i class="fas fa-temperature-high"></i> Fire Risk Engine</h2>
                <a href="?recalculate=1" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Recalculate</a>
            </div>
            <div class="card-body">
                <p>Predictive risk based on historical fires, climate indicators, and vegetation density.</p>
            </div>
        </div>

        <div class="grid-2">
        <?php
        $pdo = getPDO();
        foreach ($results as $r):
            $stmt = $pdo->prepare('SELECT forest_name, area_sq_km FROM forests WHERE forest_id = ?');
            $stmt->execute([$r['forest_id']]);
            $forest = $stmt->fetch();
            $color = $riskColors[$r['risk_level']] ?? '#999';
        ?>
            <div class="card">
                <div class="card-header">
                    <h3><?= sanitize($forest['forest_name'] ?? 'Forest #' . $r['forest_id']) ?></h3>
                    <span class="badge" style="background:<?= $color ?>;color:#fff;text-transform:uppercase;"><?= sanitize($r['risk_level']) ?></span>
                </div>
                <div class="card-body">
                    <div class="risk-meter mb-3">
                        <div class="risk-bar" style="width:<?= min(100, $r['risk_score']) ?>%;background:<?= $color ?>"></div>
                    </div>
                    <p><strong>Risk Score:</strong> <?= $r['risk_score'] ?>%</p>
                    <p><strong>Ranking:</strong> #<?= array_search($r, $results) + 1 ?> of <?= count($results) ?></p>
                    <details>
                        <summary>Risk Factors</summary>
                        <ul class="mt-2">
                            <li>Fire Incidents: <?= round($r['factors']['fireFactor'], 1) ?>%</li>
                            <li>Dryness Index: <?= round($r['factors']['tempFactor'], 1) ?>%</li>
                            <li>Moisture Level: <?= round($r['factors']['humidityFactor'], 1) ?>%</li>
                            <li>Precipitation Deficit: <?= round($r['factors']['rainfallFactor'], 1) ?>%</li>
                            <li>Vegetation: <?= round($r['factors']['vegFactor'], 1) ?>%</li>
                        </ul>
                    </details>
                    <div class="alert alert-warning mt-2" style="font-size:0.85rem;">
                        <strong>Recommended:</strong> <?= sanitize($r['recommendations']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</main>
<style>.risk-meter{background:#eee;border-radius:8px;height:12px;overflow:hidden;}.risk-bar{height:100%;border-radius:8px;transition:width 0.5s;}</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
