<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/ReportEngine.php';
requireLogin();

$pageTitle = 'Report Generator';
$pdo = getPDO();
$forests = $pdo->query('SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name')->fetchAll();
$incidents = $pdo->query(
    "SELECT incident_id, category, incident_date, forest_id
     FROM incidents
     WHERE status='approved' AND deleted_at IS NULL
     ORDER BY incident_date DESC"
)->fetchAll();

$category = $_GET['category'] ?? 'forests';
$forestScope = $_GET['forest_scope'] ?? 'all';
$forestId = $forestScope === 'single' ? (int)($_GET['forest_id'] ?? 0) : null;
$incidentId = (int)($_GET['incident_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$fileFormat = $_GET['file_format'] ?? 'html';
$download = isset($_GET['download']);

if ($category === 'users' && !canManageUsers()) {
    $category = 'forests';
    setFlash('error', 'Users reports are available to admins only.');
}

$report = null;
if (isset($_GET['generate'])) {
    $report = ReportEngine::generateReport(
        $category,
        $forestId ?: null,
        $incidentId ?: null,
        $dateFrom ?: null,
        $dateTo ?: null
    );

    if ($download && in_array($fileFormat, ['csv', 'pdf'], true)) {
        $ext = $fileFormat === 'csv' ? 'csv' : 'html';
        $filename = $category . '_report_' . date('Y-m-d') . '.' . $ext;
        ReportEngine::exportReport($report, $fileFormat, $filename);
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('error')): ?><div class="flash flash-error"><?= sanitize($msg) ?></div><?php endif; ?>
        <div class="card mb-3">
            <div class="card-header"><h2><i class="fas fa-file-lines"></i> Report Generator</h2></div>
            <div class="card-body">
                <form method="GET" id="reportForm" class="row g-3">
                    <input type="hidden" name="generate" value="1">

                    <div class="col-md-3">
                        <label class="form-label">Forest Scope</label>
                        <select name="forest_scope" id="forestScope" class="form-select">
                            <option value="all" <?= $forestScope === 'all' ? 'selected' : '' ?>>All Forests</option>
                            <option value="single" <?= $forestScope === 'single' ? 'selected' : '' ?>>Single Forest</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="forestSelectWrap" style="<?= $forestScope !== 'single' ? 'display:none;' : '' ?>">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" id="forest_id" class="form-select">
                            <option value="">Select forest</option>
                            <?php foreach ($forests as $f): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestId === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Report Category</label>
                        <select name="category" id="reportCategory" class="form-select">
                            <option value="forests" <?= $category === 'forests' ? 'selected' : '' ?>>List of Forests</option>
                            <option value="animals" <?= $category === 'animals' ? 'selected' : '' ?>>List of Animals</option>
                            <option value="animal_population" <?= $category === 'animal_population' ? 'selected' : '' ?>>Animal Population</option>
                            <option value="incidents" <?= $category === 'incidents' ? 'selected' : '' ?>>List of Incidents</option>
                            <option value="single_incident" <?= $category === 'single_incident' ? 'selected' : '' ?>>Single Incident</option>
                            <?php if (canManageUsers()): ?>
                            <option value="users" <?= $category === 'users' ? 'selected' : '' ?>>Users List</option>
                            <?php endif; ?>
                            <option value="birth_death" <?= $category === 'birth_death' ? 'selected' : '' ?>>Birth & Death (by Forest)</option>
                        </select>
                    </div>

                    <div class="col-md-3" id="incidentSelectWrap" style="<?= $category !== 'single_incident' ? 'display:none;' : '' ?>">
                        <label class="form-label">Select Incident</label>
                        <select name="incident_id" class="form-select" id="incidentSelect">
                            <?php foreach ($incidents as $i): ?>
                            <option value="<?= (int)$i['incident_id'] ?>" data-forest-id="<?= (int)$i['forest_id'] ?>" <?= $incidentId === (int)$i['incident_id'] ? 'selected' : '' ?>>
                                #<?= $i['incident_id'] ?> - <?= sanitize($i['category']) ?> (<?= $i['incident_date'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3" id="dateRangeWrap" style="<?= !in_array($category, ['incidents', 'birth_death'], true) ? 'display:none;' : '' ?>">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= sanitize($dateFrom) ?>">
                    </div>
                    <div class="col-md-3" id="dateToWrap" style="<?= !in_array($category, ['incidents', 'birth_death'], true) ? 'display:none;' : '' ?>">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= sanitize($dateTo) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">File Type</label>
                        <select name="file_format" class="form-select">
                            <option value="html" <?= $fileFormat === 'html' ? 'selected' : '' ?>>Preview (HTML)</option>
                            <option value="csv" <?= $fileFormat === 'csv' ? 'selected' : '' ?>>CSV</option>
                            <option value="pdf" <?= $fileFormat === 'pdf' ? 'selected' : '' ?>>PDF</option>
                        </select>
                    </div>

                    <div class="col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Generate Report</button>
                        <button type="submit" name="download" value="1" class="btn btn-success"><i class="fas fa-download"></i> Download Report</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report): ?>
        <div class="card printable-report">
            <div class="card-header d-flex justify-content-between">
                <h2><?= sanitize($report['title'] ?? 'Report') ?></h2>
                <?php if (empty($report['is_single_incident'])): ?>
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($report['is_single_incident']) && !empty($report['incident'])): $i = $report['incident']; ?>
                <div class="single-incident-report">
                    <div class="row mb-3">
                        <div class="col-md-6"><strong>Incident ID:</strong> #<?= (int)$i['incident_id'] ?></div>
                        <div class="col-md-6"><strong>Category:</strong> <?= sanitize($i['incident_category']) ?></div>
                        <div class="col-md-6"><strong>Date of Incident:</strong> <?= sanitize($i['incident_date']) ?></div>
                        <div class="col-md-6"><strong>Current Status:</strong> <?= sanitize($i['current_status']) ?></div>
                        <div class="col-md-6"><strong>Reported By:</strong> <?= sanitize($i['reported_by'] ?? 'N/A') ?></div>
                        <div class="col-md-6"><strong>Verified By:</strong> <?= sanitize($i['verified_by'] ?? 'N/A') ?></div>
                    </div>
                    <p><strong>Description:</strong></p>
                    <p><?= sanitize($i['description']) ?></p>
                    <?php if (!empty($i['image_path'])): ?>
                    <img src="<?= sanitize($i['image_path']) ?>" class="report-image" alt="Incident">
                    <?php endif; ?>
                </div>
                <?php elseif (!empty($report['grouped']) && !empty($report['grouped_data'])): ?>
                    <?php foreach ($report['grouped_data'] as $forestName => $rows): ?>
                    <h3 class="report-group-heading mt-3"><?= sanitize($forestName) ?></h3>
                    <div class="table-wrap mb-4">
                        <table class="data-table">
                            <thead><tr><?php foreach ($report['headers'] as $h): ?><th><?= sanitize($h) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr><?php foreach ($row as $cell): ?><td><?= sanitize((string)$cell) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                <?php elseif (!empty($report['rows'])): ?>
                <div class="table-wrap">
                    <table class="data-table datatable">
                        <thead><tr><?php foreach ($report['headers'] as $h): ?><th><?= sanitize($h) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($report['rows'] as $row): ?>
                        <tr><?php foreach ($row as $cell): ?><td><?= sanitize((string)$cell) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No data found for the selected criteria.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
document.getElementById('forestScope').addEventListener('change', function() {
    document.getElementById('forestSelectWrap').style.display = this.value === 'single' ? '' : 'none';
    filterIncidentsByForest();
});
document.getElementById('reportCategory').addEventListener('change', function() {
    const cat = this.value;
    document.getElementById('incidentSelectWrap').style.display = cat === 'single_incident' ? '' : 'none';
    const showDates = cat === 'incidents' || cat === 'birth_death';
    document.getElementById('dateRangeWrap').style.display = showDates ? '' : 'none';
    document.getElementById('dateToWrap').style.display = showDates ? '' : 'none';
    filterIncidentsByForest();
});
document.getElementById('forest_id')?.addEventListener('change', filterIncidentsByForest);

function filterIncidentsByForest() {
    const forestScope = document.getElementById('forestScope').value;
    const forestSelect = document.getElementById('forest_id');
    const forestId = forestScope === 'single' && forestSelect ? parseInt(forestSelect.value || '0') : 0;
    const incidentSelect = document.getElementById('incidentSelect');
    
    if (!incidentSelect) return;
    
    const options = Array.from(incidentSelect.querySelectorAll('option'));
    let firstVisible = null;
    options.forEach(option => {
        const optionForestId = parseInt(option.getAttribute('data-forest-id'));
        const visible = forestScope === 'all' || forestId === 0 || optionForestId === forestId;
        option.hidden = !visible;
        option.disabled = !visible;
        if (visible && !firstVisible) {
            firstVisible = option;
        }
        if (!visible && option.selected) {
            option.selected = false;
        }
    });

    if (firstVisible && !options.some(option => option.selected && !option.disabled)) {
        firstVisible.selected = true;
    }
}

filterIncidentsByForest();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
