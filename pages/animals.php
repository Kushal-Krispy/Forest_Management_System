<?php
/**
 * ANIMALS LIST
 * ------------
 * TABLE: animals (PK: animal_id, FK: forest_id, added_by)
 * JOIN: forests for forest name, users for officer name
 * DISPLAY: image from animals.image_path column
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Animals';
$conn = getDBConnection();

$forests = $conn->query(
    "SELECT forest_id, forest_name FROM forests WHERE deleted_at IS NULL ORDER BY forest_name"
);

$forestFilter = isset($_GET['forest_id']) ? (int)$_GET['forest_id'] : 0;

$sql = "SELECT a.*, f.forest_name, u.full_name AS added_by_name
        FROM animals a
        JOIN forests f ON a.forest_id = f.forest_id
        JOIN users u ON a.added_by = u.user_id
        WHERE a.deleted_at IS NULL";
if ($forestFilter > 0) {
    $sql .= " AND a.forest_id = " . $forestFilter;
}
$sql .= " ORDER BY a.common_name";
$animals = $conn->query($sql);
$conn->close();

function statusBadgeClass(string $status): string {
    return match ($status) {
        'Endangered', 'Critically Endangered' => 'badge-endangered',
        'Vulnerable', 'Near Threatened' => 'badge-vulnerable',
        default => 'badge-lc',
    };
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="data-flow-box">
            <strong>Data transparency:</strong> Retrieved via JOIN on <code>animals</code> + <code>forests</code> + <code>users</code>.
            Images stored at path in <code>animals.image_path</code> (uploaded by officer/admin in Update Animal page).
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Select Forest</label>
                        <select name="forest_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Forests</option>
                            <?php while ($f = $forests->fetch_assoc()): ?>
                            <option value="<?= (int)$f['forest_id'] ?>" <?= $forestFilter === (int)$f['forest_id'] ? 'selected' : '' ?>>
                                <?= sanitize($f['forest_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if (canManageAnimals()): ?>
        <div style="margin-bottom:1.5rem;">
            <a href="<?= url('pages/update_animal.php') ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Animal</a>
        </div>
        <?php endif; ?>

        <div class="grid-3">
        <?php while ($a = $animals->fetch_assoc()): ?>
            <div class="animal-card">
                <img class="animal-card-img"
                     src="<?= $a['image_path'] ? sanitize($a['image_path']) : asset('images/animal-placeholder.svg') ?>"
                     alt="<?= sanitize($a['common_name']) ?>"
                     onerror="this.src='<?= asset('images/animal-placeholder.svg') ?>'">
                <div class="animal-card-body">
                    <h3><?= sanitize($a['common_name']) ?></h3>
                    <p class="animal-species"><?= sanitize($a['species_name']) ?></p>
                    <p><i class="fas fa-users"></i> Population: <strong><?= number_format((int)$a['population_estimate']) ?></strong></p>
                    <p><span class="badge <?= statusBadgeClass($a['conservation_status']) ?>"><?= sanitize($a['conservation_status']) ?></span></p>
                    <p><i class="fas fa-tree"></i> <?= sanitize($a['forest_name']) ?></p>
                    <p style="font-size:0.8rem;color:var(--text-secondary);">Added by <?= sanitize($a['added_by_name']) ?> · ID: <?= (int)$a['animal_id'] ?></p>
                    <?php if (canManageAnimals()): ?>
                    <div style="margin-top:0.75rem;">
                        <form action="<?= url('actions/animal_action.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this animal?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="animal_id" value="<?= (int)$a['animal_id'] ?>">
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
