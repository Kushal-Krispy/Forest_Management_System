<?php
/**
 * REPORT INCIDENT - Public users only
 * STORES IN: pending_incidents (awaiting officer approval)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['public']);

$pageTitle = 'Report Incident';
$conn = getDBConnection();
$forests = $conn->query('SELECT forest_id, forest_name FROM forests ORDER BY forest_name');

$myReports = $conn->prepare(
    "SELECT pending_id, category, status, submitted_at FROM pending_incidents WHERE reported_by = ? ORDER BY submitted_at DESC"
);
$userId = getSessionUserId();
$myReports->bind_param('i', $userId);
$myReports->execute();
$myReportsResult = $myReports->get_result();
$conn->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <?php if ($msg = getFlash('success')): ?><div class="flash flash-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <?php if ($msg = getFlash('error')): ?><div class="flash flash-error"><?= sanitize($msg) ?></div><?php endif; ?>

        <div class="data-flow-box">
            <strong>Public report flow:</strong>
            1. You upload photo + description → <code>pending_incidents</code> table (status: pending)<br>
            2. Forest officer logs in → <strong>Pending Reports</strong> → views your uploaded photo<br>
            3. Officer approves → stored procedure moves record to <code>incidents</code> table
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-camera"></i> Submit Incident Report</h2></div>
            <div class="card-body">
                <form action="<?= url('actions/incident_action.php') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="public_report">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Forest (optional)</label>
                            <select name="forest_id">
                                <option value="">-- Select if known --</option>
                                <?php while ($f = $forests->fetch_assoc()): ?>
                                <option value="<?= (int)$f['forest_id'] ?>"><?= sanitize($f['forest_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Incident Type</label>
                            <select name="category" required>
                                <option>Wildfire</option>
                                <option>Poaching</option>
                                <option>Illegal Logging</option>
                                <option>Wildlife Conflict</option>
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
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Photo Evidence (required)</label>
                            <input type="file" name="incident_image" accept="image/*" required data-preview="pubPreview">
                            <img id="pubPreview" class="img-preview" style="display:none;margin-top:10px;">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Description</label>
                            <textarea name="description" rows="4" required placeholder="Describe what you observed..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Report</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:1.5rem;">
            <div class="card-header"><h2>My Submitted Reports</h2></div>
            <div class="card-body table-wrap">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Category</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php while ($r = $myReportsResult->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$r['pending_id'] ?></td>
                        <td><?= sanitize($r['category']) ?></td>
                        <td>
                            <span class="badge badge-<?= $r['status'] === 'pending' ? 'pending' : ($r['status'] === 'approved' ? 'approved' : 'endangered') ?>"><?= sanitize($r['status']) ?></span>
                            <?php if ($r['status'] === 'rejected' && !empty($r['review_notes'])): ?>
                            <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">Reason: <?= sanitize($r['review_notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= sanitize($r['submitted_at']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
