<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'QR Wildlife Tracking';
$pdo = getPDO();
$forests = $pdo->query('SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name')->fetchAll();
$forestFilter = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;

$sql = 'SELECT a.*, f.forest_name FROM animals a JOIN forests f ON a.forest_id = f.forest_id
        WHERE a.deleted_at IS NULL';
if ($forestFilter > 0) {
    $sql .= ' AND a.forest_id = ' . $forestFilter;
}
$sql .= ' ORDER BY a.common_name';
$animals = $pdo->query($sql)->fetchAll();

$extraJs = [asset('js/pages/qr_tracking.js')];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Forests</option>
                            <?php foreach ($forests as $f): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestFilter === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-qrcode"></i> Scan QR Code</h2></div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label>Enter QR Code or Animal ID</label>
                        <input type="text" id="qrInput" class="form-control" placeholder="FMS-ANM-00001">
                    </div>
                    <button id="qrLookup" class="btn btn-primary">Lookup Animal</button>
                    <div id="qrResult" class="mt-3"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Record Scan Action</h2></div>
                <div class="card-body">
                    <form id="scanForm">
                        <input type="hidden" id="scanAnimalId">
                        <div class="form-group mb-2">
                            <label>Action Type</label>
                            <select id="scanType" class="form-select">
                                <option value="health">Update Health</option>
                                <option value="location">Update Location</option>
                                <option value="sighting">Add Sighting</option>
                                <option value="relocation">Record Relocation</option>
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label>Health Status</label>
                            <select id="healthStatus" class="form-select">
                                <option value="healthy">Healthy</option>
                                <option value="injured">Injured</option>
                                <option value="sick">Sick</option>
                                <option value="critical">Critical</option>
                                <option value="deceased">Deceased</option>
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label>Notes</label>
                            <textarea id="scanNotes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Submit Scan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h2>Registered Animals & QR Codes<?= $forestFilter ? ' (Filtered)' : '' ?></h2></div>
            <div class="card-body">
                <?php if (empty($animals)): ?>
                <p class="text-muted">No animals found for the selected forest.</p>
                <?php else: ?>
                <div class="row g-3">
                <?php foreach ($animals as $a): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="qr-display" data-qr="<?= sanitize($a['qr_code']) ?>">
                                    <i class="fas fa-qrcode fa-4x text-success"></i>
                                    <p class="mt-2 font-monospace"><?= sanitize($a['qr_code']) ?></p>
                                </div>
                                <h5><?= sanitize($a['common_name']) ?></h5>
                                <p class="text-muted small"><?= sanitize($a['species_name']) ?></p>
                                <p><span class="badge bg-secondary"><?= sanitize($a['forest_name']) ?></span>
                                   <span class="badge bg-info"><?= sanitize($a['health_status']) ?></span></p>
                                <button class="btn btn-sm btn-outline-primary select-animal" data-id="<?= (int)$a['animal_id'] ?>" data-qr="<?= sanitize($a['qr_code']) ?>">Select</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
