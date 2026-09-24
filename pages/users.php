<?php
/**
 * USERS LIST - Admin only
 * TABLE: users (PK: user_id)
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$pageTitle = 'Users List';
ensureSimplifiedUserRoles();
$pdo = getPDO();
$users = $pdo->query(
    "SELECT user_id, username, email, full_name, role, is_active, created_at
     FROM users
     WHERE deleted_at IS NULL
     ORDER BY FIELD(role, 'admin', 'officer', 'public'), username"
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
            <strong>Data transparency:</strong> All accounts in <code>users</code> table.
            Registration inserts with role=<code>public</code>. Admin can promote public users to <code>officer</code> or demote officers to <code>public</code>.
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2>Promote/Demote User</h2></div>
            <div class="card-body">
                <form action="<?= url('actions/user_action.php') ?>" method="POST" class="grid-3 align-end">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label>User ID</label>
                        <input type="number" name="user_id" required placeholder="From table below">
                    </div>
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" required>
                            <option value="promote">Promote to Officer</option>
                            <option value="demote">Demote to Public</option>
                        </select>
                    </div>
                    <div><button type="submit" class="btn btn-primary">Submit</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-users"></i> System Users</h2></div>
            <div class="card-body table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>PK (user_id)</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): $role = normalizeRoleValue($u['role']); ?>
                    <tr>
                        <td><?= (int)$u['user_id'] ?></td>
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['full_name']) ?></td>
                        <td><?= sanitize($u['email']) ?></td>
                        <td><span class="badge badge-<?= sanitize($role) ?>"><?= sanitize(roleLabel($role)) ?></span></td>
                        <td><?= $u['is_active'] ? 'Yes' : 'No' ?></td>
                        <td><?= sanitize($u['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
