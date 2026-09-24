<?php
/**
 * PENDING REPORTS - Officer/Admin reviews public submissions
 * TABLE: pending_incidents (photo visible here for officer verification)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Pending Reports';
$conn = getDBConnection();

$pending = $conn->query(
    "SELECT p.*, u.full_name AS reporter_name, u.email AS reporter_email, f.forest_name
     FROM pending_incidents p
     JOIN users u ON p.reported_by = u.user_id
     LEFT JOIN forests f ON p.forest_id = f.forest_id
     WHERE p.status = 'pending'
     ORDER BY p.submitted_at ASC"
);
$forests = $conn->query('SELECT forest_id, forest_name FROM forests ORDER BY forest_name');
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
            <strong>Officer workflow:</strong> Public photo stored at <code>pending_incidents.image_path</code>.
            On Approve → <code>CALL sp_approve_pending_incident(pending_id, reviewer_id, forest_id)</code>
            copies data to <code>incidents</code> and sets pending status to approved.
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Awaiting Verification</h2>
            </div>
            <?php if ($pending->num_rows === 0): ?>
            <div class="card-body empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No pending reports. All caught up!</p>
            </div>
            <?php else: ?>
            <?php while ($p = $pending->fetch_assoc()): ?>
            <div class="pending-card">
                <a href="<?= sanitize($p['image_path']) ?>" target="_blank">
                    <img src="<?= sanitize($p['image_path']) ?>" alt="Public report photo">
                </a>
                <div>
                    <h3><?= sanitize($p['category']) ?></h3>
                    <p><?= sanitize($p['description']) ?></p>
                    <p><strong>Reporter:</strong> <?= sanitize($p['reporter_name']) ?> (<?= sanitize($p['reporter_email']) ?>)</p>
                    <p><strong>Suggested forest:</strong> <?= sanitize($p['forest_name'] ?? 'Not specified') ?></p>
                    <p><strong>Submitted:</strong> <?= sanitize($p['submitted_at']) ?> · PK: pending_id=<?= (int)$p['pending_id'] ?></p>

                    <form action="<?= url('actions/incident_action.php') ?>" method="POST" style="margin-top:1rem;display:inline-block;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="pending_id" value="<?= (int)$p['pending_id'] ?>">
                        <div class="form-group" style="max-width:280px;">
                            <label>Assign Forest (required for approval)</label>
                            <select name="forest_id" required>
                                <?php
                                $forests->data_seek(0);
                                while ($f = $forests->fetch_assoc()):
                                ?>
                                <option value="<?= (int)$f['forest_id'] ?>" <?= ($p['forest_id'] == $f['forest_id']) ? 'selected' : '' ?>>
                                    <?= sanitize($f['forest_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:280px;">
                            <label>Edit Description (optional)</label>
                            <textarea name="edit_description" rows="2" placeholder="Modify description if needed"><?= sanitize($p['description']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Approve</button>
                    </form>

                    <form action="<?= url('actions/incident_action.php') ?>" method="POST" style="display:inline-block;margin-left:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="pending_id" value="<?= (int)$p['pending_id'] ?>">
                        <input type="text" name="review_notes" placeholder="Rejection reason (optional)" style="padding:6px 10px;margin-right:6px;">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Reject this report?"><i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
