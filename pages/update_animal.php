<?php
/**
 * UPDATE ANIMAL - Admin & Officer only
 * TABLE: animals | Officer can upload custom photo -> animals.image_path
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'officer']);

$pageTitle = 'Update Animal';
$conn = getDBConnection();
$forests = $conn->query('SELECT forest_id, forest_name FROM forests ORDER BY forest_name');
$editId = (int)($_GET['edit'] ?? 0);
$editAnimal = null;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM animals WHERE animal_id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editAnimal = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
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
            <strong>Data flow:</strong> Form POST → <code>actions/animal_action.php</code> →
            <code>INSERT/UPDATE animals</code> (FK: forest_id, added_by).
            Image file → <code>assets/uploads/animals/</code> → path saved in <code>animals.image_path</code>.
            Trigger <code>trg_animals_after_insert/update</code> writes to <code>audit_log</code>.
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-paw"></i> <?= $editAnimal ? 'Edit Animal #' . (int)$editAnimal['animal_id'] : 'Add New Animal' ?></h2>
            </div>
            <div class="card-body">
                <form action="<?= url('actions/animal_action.php') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="animal_id" value="<?= $editAnimal['animal_id'] ?? '' ?>">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Forest (Foreign Key)</label>
                            <select name="forest_id" required>
                                <option value="">Select forest</option>
                                <?php while ($f = $forests->fetch_assoc()): ?>
                                <option value="<?= (int)$f['forest_id'] ?>" <?= ($editAnimal && $editAnimal['forest_id'] == $f['forest_id']) ? 'selected' : '' ?>>
                                    <?= sanitize($f['forest_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Common Name</label>
                            <input type="text" name="common_name" value="<?= sanitize($editAnimal['common_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Species Name (Scientific)</label>
                            <input type="text" name="species_name" value="<?= sanitize($editAnimal['species_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Population Estimate</label>
                            <input type="number" name="population_estimate" min="0" value="<?= (int)($editAnimal['population_estimate'] ?? 0) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Conservation Status</label>
                            <select name="conservation_status" required>
                                <?php foreach (['Least Concern','Near Threatened','Vulnerable','Endangered','Critically Endangered'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($editAnimal && $editAnimal['conservation_status'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Animal Photo (Officer/Admin upload)</label>
                            <input type="file" name="animal_image" accept="image/*" data-preview="animalPreview">
                            <p class="form-hint">Custom photo from officer — saved to uploads/animals/</p>
                            <?php if (!empty($editAnimal['image_path'])): ?>
                            <img id="animalPreview" class="img-preview" src="<?= sanitize($editAnimal['image_path']) ?>" style="margin-top:10px;display:block;">
                            <?php else: ?>
                            <img id="animalPreview" class="img-preview" style="display:none;margin-top:10px;">
                            <?php endif; ?>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Notes</label>
                            <textarea name="notes" rows="3"><?= sanitize($editAnimal['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Animal</button>
                    <a href="<?= url('pages/animals.php') ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:1.5rem;">
            <div class="card-header"><h2>Quick Edit Existing</h2></div>
            <div class="card-body table-wrap">
                <?php
                $conn = getDBConnection();
                $list = $conn->query('SELECT animal_id, common_name, species_name FROM animals ORDER BY common_name');
                $conn->close();
                ?>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Species</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while ($a = $list->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$a['animal_id'] ?></td>
                        <td><?= sanitize($a['common_name']) ?></td>
                        <td><?= sanitize($a['species_name']) ?></td>
                        <td><a href="?edit=<?= (int)$a['animal_id'] ?>" class="btn btn-secondary btn-sm">Edit</a></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
