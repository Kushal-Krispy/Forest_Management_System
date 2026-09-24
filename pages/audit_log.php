<?php
/**
 * LOGS - Shows trigger-generated logs (transparency)
 * TABLE: audit_log (FK: performed_by -> users)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Logs';
$conn = getDBConnection();
$logs = $conn->query(
    "SELECT a.*, u.full_name FROM audit_log a
     LEFT JOIN users u ON a.performed_by = u.user_id
     ORDER BY a.created_at DESC LIMIT 100"
);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="data-flow-box">
            <strong>Triggers in action:</strong> Entries here are auto-created by MySQL TRIGGERS
            (<code>trg_animals_after_insert</code>, <code>trg_pending_after_insert</code>, etc.)
            — no manual PHP logging required.
        </div>
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-list-check"></i> System Logs (last 100)</h2></div>
            <div class="card-body table-wrap">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Table</th><th>Record</th><th>Action</th><th>Details</th><th>By</th><th>When</th></tr></thead>
                    <tbody>
                    <?php while ($l = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$l['log_id'] ?></td>
                        <td><code><?= sanitize($l['table_name']) ?></code></td>
                        <td><?= (int)$l['record_id'] ?></td>
                        <td><?= sanitize($l['action_type']) ?></td>
                        <td><?= sanitize($l['details']) ?></td>
                        <td><?= sanitize($l['full_name'] ?? 'System') ?></td>
                        <td><?= sanitize($l['created_at']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
