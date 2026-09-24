<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Forest GIS Map';
$extraCss = asset('css/pages/map.css');
$extraJs = [asset('js/pages/forest_map.js')];
$canDeleteForest = canManageForests();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search by Name/Code</label>
                        <input type="text" id="mapSearch" class="form-control" placeholder="Name, code, region...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Forest ID</label>
                        <input type="number" id="forestIdSearch" class="form-control" placeholder="Enter ID">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Filter Layer</label>
                        <select id="mapLayer" class="form-select">
                            <option value="all">All Markers</option>
                            <option value="forests">Forests Only</option>
                            <option value="wildlife">Wildlife Only</option>
                            <option value="incidents">Incidents Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Incident Type</label>
                        <select id="incidentType" class="form-select">
                            <option value="">All Types</option>
                            <option value="fire">Fire</option>
                            <option value="poach">Poaching</option>
                            <option value="log">Illegal Logging</option>
                            <option value="wildlife">Wildlife</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button id="mapRefresh" class="btn btn-primary flex-grow-1"><i class="fas fa-sync"></i> Refresh</button>
                        <?php if ($canDeleteForest): ?>
                        <button id="deleteForestBtn" class="btn btn-danger is-hidden"><i class="fas fa-trash"></i> Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div id="mapStatus" class="map-status" role="status" aria-live="polite"></div>
        <div id="forestMap" class="map-container"></div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
