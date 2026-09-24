<?php
/**
 * FOREST INFORMATION
 * ------------------
 * TABLE: forests (PK: forest_id, FK: created_by -> users)
 * Admin/officer can add/edit; all roles can view.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Forest Information';
$pdo = getPDO();
$forests = $pdo->query(
    "SELECT f.*, u.full_name AS creator_name
     FROM forests f
     JOIN users u ON f.created_by = u.user_id
     WHERE f.deleted_at IS NULL
     ORDER BY f.forest_name"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('success')): ?><div class="flash flash-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <?php if ($msg = getFlash('error')): ?><div class="flash flash-error"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="data-flow-box">
            <strong>Data transparency:</strong> Forest records are stored in table <code>forests</code>.
            Primary key <code>forest_id</code> links to <code>animals.forest_id</code> and <code>incidents.forest_id</code> (Foreign Keys).
            <?php if (canManageForests()): ?>Admin/officer submits form -> <code>actions/forest_action.php</code> -> INSERT/UPDATE.<?php endif; ?>
        </div>

        <?php if (canManageForests()): ?>
        <div class="card mb-3">
            <div class="card-header"><h2><i class="fas fa-plus"></i> Add / Edit Forest</h2></div>
            <div class="card-body">
                <form action="<?= url('actions/forest_action.php') ?>" method="POST" class="grid-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="forest_id" id="forest_id" value="">
                    <div class="form-group">
                        <label>Forest Name</label>
                        <input type="text" name="forest_name" id="forest_name" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="location" required>
                    </div>
                    <div class="form-group">
                        <label>Area (sq km)</label>
                        <input type="number" step="0.01" name="area_sq_km" id="area_sq_km" required>
                    </div>
                    <div class="form-group">
                        <label>Ecosystem Type</label>
                        <input type="text" name="ecosystem_type" id="ecosystem_type" required>
                    </div>
                    <div class="form-group">
                        <label>Forest Code</label>
                        <input type="text" name="forest_code" id="forest_code" placeholder="FR-0001">
                    </div>
                    <div class="form-group">
                        <label>Region</label>
                        <input type="text" name="region" id="region">
                    </div>
                    <div class="form-group">
                        <label>Latitude</label>
                        <input type="number" step="0.00000001" name="latitude" id="latitude">
                    </div>
                    <div class="form-group">
                        <label>Longitude</label>
                        <input type="number" step="0.00000001" name="longitude" id="longitude">
                    </div>
                    <div class="form-group">
                        <label>Established Year</label>
                        <input type="number" name="established_year" id="established_year">
                    </div>
                    <div class="form-group grid-span-full">
                        <label>Description</label>
                        <textarea name="description" id="description" rows="3"></textarea>
                    </div>
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Forest</button>
                        <button type="button" class="btn btn-secondary" onclick="clearForestForm()">Clear</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid-2">
        <?php foreach ($forests as $f): ?>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-tree"></i> <?= sanitize($f['forest_name']) ?></h2>
                    <?php if (canManageForests()): ?>
                    <button class="btn btn-secondary btn-sm" onclick='editForest(<?= json_encode($f) ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p><strong>Location:</strong> <?= sanitize($f['location']) ?></p>
                    <p><strong>Area:</strong> <?= sanitize($f['area_sq_km']) ?> sq km</p>
                    <p><strong>Ecosystem:</strong> <?= sanitize($f['ecosystem_type']) ?></p>
                    <?php if ($f['established_year']): ?>
                    <p><strong>Established:</strong> <?= (int)$f['established_year'] ?></p>
                    <?php endif; ?>
                    <p><?= sanitize($f['description'] ?? '') ?></p>
                    <p class="meta-text mt-3">
                        ID: <?= (int)$f['forest_id'] ?> · Updated: <?= sanitize($f['updated_at']) ?> · By: <?= sanitize($f['creator_name']) ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</main>
<?php
$extraJs = asset('js/forest.js');
require_once __DIR__ . '/../includes/footer.php';
?>
