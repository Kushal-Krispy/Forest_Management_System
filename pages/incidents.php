<?php
/**
 * INCIDENTS - Approved records
 * TABLE: incidents (JOIN forests, users for reporter/approver)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Incidents';
$conn = getDBConnection();

$forestFilter = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;
$categoryFilter = trim($_GET['category'] ?? '');

$forests = $conn->query("SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name");
$categories = $conn->query("SELECT DISTINCT category FROM incidents WHERE status='approved' ORDER BY category");

$sql = "SELECT i.*, f.forest_name, ur.full_name AS reporter, ua.full_name AS approver
        FROM incidents i
        LEFT JOIN forests f ON i.forest_id = f.forest_id
        JOIN users ur ON i.reported_by = ur.user_id
        LEFT JOIN users ua ON i.approved_by = ua.user_id
        WHERE i.status = 'approved'";
if ($forestFilter > 0) {
    $sql .= " AND i.forest_id = " . $forestFilter;
}
if ($categoryFilter !== '') {
    $sql .= " AND i.category = '" . $conn->real_escape_string($categoryFilter) . "'";
}
$sql .= " ORDER BY i.incident_date DESC";
$incidents = $conn->query($sql);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="data-flow-box">
            <strong>Data transparency:</strong> Displayed from <code>incidents</code> where <code>status='approved'</code>.
            Public reports move here after officer approves via <code>sp_approve_pending_incident</code> procedure.
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select">
                            <option value="">All Forests</option>
                            <?php while ($f = $forests->fetch_assoc()): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestFilter === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Incident Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= sanitize($c['category']) ?>" <?= $categoryFilter === $c['category'] ? 'selected' : '' ?>>
                                <?= sanitize($c['category']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid-2">
        <?php while ($i = $incidents->fetch_assoc()): ?>
            <div class="card">
                <?php if ($i['image_path']): ?>
                <img src="<?= sanitize($i['image_path']) ?>" style="width:100%;height:200px;object-fit:cover;" alt="Incident">
                <?php endif; ?>
                <div class="card-body">
                    <h3><?= sanitize($i['category']) ?></h3>
                    <p><span class="badge badge-approved"><?= sanitize($i['source']) ?></span></p>
                    <p><?= sanitize($i['description']) ?></p>
                    <p><strong>Forest:</strong> <?= sanitize($i['forest_name'] ?? 'Unassigned') ?></p>
                    <p><strong>Date:</strong> <?= sanitize($i['incident_date']) ?></p>
                    <p style="font-size:0.8rem;color:var(--text-secondary);">
                        PK: <?= (int)$i['incident_id'] ?> · Reporter: <?= sanitize($i['reporter']) ?>
                        <?= $i['approver'] ? ' · Approved by: ' . sanitize($i['approver']) : '' ?>
                    </p>
                    <?php if (canManageIncidents()): ?>
                    <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editIncident(<?= (int)$i['incident_id'] ?>, '<?= sanitize($i['category']) ?>', '<?= sanitize($i['severity'] ?? 'medium') ?>', '<?= sanitize($i['description']) ?>', <?= (int)($i['forest_id'] ?? 0) ?>, '<?= sanitize($i['incident_date']) ?>')"><i class="fas fa-edit"></i> Edit</button>
                        <form action="<?= url('actions/incident_action.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this incident?');" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="incident_id" value="<?= (int)$i['incident_id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</main>

<!-- Edit Incident Modal -->
<div class="modal" id="editIncidentModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Incident</h3>
                <button type="button" class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form action="<?= url('actions/incident_action.php') ?>" method="POST" id="editIncidentForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="incident_id" id="edit_incident_id">
                    <div class="form-group">
                        <label>Forest</label>
                        <select name="forest_id" id="edit_forest_id" required>
                            <?php 
                            $forests->data_seek(0);
                            while ($f = $forests->fetch_assoc()): 
                            ?>
                            <option value="<?= (int)$f['forest_id'] ?>"><?= sanitize($f['forest_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="edit_category" required>
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
                        <select name="severity" id="edit_severity" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Incident Date</label>
                        <input type="date" name="incident_date" id="edit_incident_date" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Incident</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-dialog {
    background: var(--bg-secondary);
    border-radius: var(--radius);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
}
.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
}
.modal-body {
    padding: 1.5rem;
}
</style>

<script>
function editIncident(id, category, severity, description, forestId, incidentDate) {
    document.getElementById('edit_incident_id').value = id;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_severity').value = severity;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_forest_id').value = forestId;
    document.getElementById('edit_incident_date').value = incidentDate;
    document.getElementById('editIncidentModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editIncidentModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
