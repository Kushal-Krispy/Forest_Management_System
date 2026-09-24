<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Add Incident';
$conn = getDBConnection();
$forests = $conn->query('SELECT forest_id, forest_name FROM forests ORDER BY forest_name');
$conn->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('error')): ?><div class="flash flash-error"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="data-flow-box">
            <strong>Data flow:</strong> Officer/Admin form → <code>actions/incident_action.php</code> →
            <code>INSERT INTO incidents</code> (status=approved immediately). Image → <code>uploads/incidents/</code>.
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-file-circle-plus"></i> Record New Incident</h2></div>
            <div class="card-body">
                <form action="<?= url('actions/incident_action.php') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_official">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Forest</label>
                            <select name="forest_id" required>
                                <?php while ($f = $forests->fetch_assoc()): ?>
                                <option value="<?= (int)$f['forest_id'] ?>"><?= sanitize($f['forest_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
                                <option>Wildfire</option>
                                <option>Poaching</option>
                                <option>Illegal Logging</option>
                                <option>Wildlife Conflict</option>
                                <option>Flooding</option>
                                <option>Deforestation</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Severity</label>
                            <select name="severity" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Incident Date</label>
                            <input type="date" name="incident_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Incident Photo</label>
                            <input type="file" name="incident_image" accept="image/*" data-preview="incPreview">
                            <img id="incPreview" class="img-preview" style="display:none;margin-top:10px;">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Description</label>
                            <textarea name="description" rows="4" required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Incident</button>
                </form>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
