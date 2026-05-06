<?php 
$page = 'subjects';
$pageTitle = 'Subjects';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    $db->insert('subjects', [
        'uuid' => generateUUID(),
        'name' => $_POST['name'],
        'code' => $_POST['code'],
        'teacher_id' => $_POST['teacher_id'] ?? null,
        'class_id' => $_POST['class_id'] ?? null,
        'credit_hours' => $_POST['credit_hours'] ?? 1,
        'sync_status' => 'pending'
    ]);
    redirect(SITE_URL . '/subjects/');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subject'])) {
    $db->update('subjects', [
        'name' => $_POST['name'],
        'code' => $_POST['code'],
        'teacher_id' => $_POST['teacher_id'] ?: null,
        'class_id' => $_POST['class_id'] ?: null,
        'credit_hours' => $_POST['credit_hours'] ?? 1,
        'sync_status' => 'pending'
    ], 'id = :id', ['id' => $_POST['subject_id']]);
    redirect(SITE_URL . '/subjects/');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    $db->delete('subjects', 'id = :id', ['id' => $_POST['subject_id']]);
    redirect(SITE_URL . '/subjects/');
}

require_once __DIR__ . '/../config/header.php';

$subjects = $db->select("
    SELECT sub.*, t.user_id, u.first_name, u.last_name, c.name as class_name
    FROM subjects sub
    LEFT JOIN teachers t ON sub.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON sub.class_id = c.id
    ORDER BY sub.name
");

$teachers = $db->select("SELECT t.*, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id");
$classes = $db->select("SELECT * FROM classes ORDER BY name");
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-book me-2"></i>Subjects</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> Add Subject
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th>Credits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><code><?= $subject['code'] ?></code></td>
                        <td><strong><?= $subject['name'] ?></strong></td>
                        <td><?= $subject['class_name'] ?? 'All Classes' ?></td>
                        <td><?= $subject['first_name'] ? $subject['first_name'] . ' ' . $subject['last_name'] : '<span class="text-muted">Not assigned</span>' ?></td>
                        <td><?= $subject['credit_hours'] ?></td>
                        <td>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#viewModal<?= $subject['id'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $subject['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $subject['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g., MATH101">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= $class['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Credit Hours</label>
                        <input type="number" name="credit_hours" class="form-control" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="create_subject" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($subjects as $subject): ?>
<!-- View Modal -->
<div class="modal fade" id="viewModal<?= $subject['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subject Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Code:</strong> <?= $subject['code'] ?></p>
                <p><strong>Name:</strong> <?= $subject['name'] ?></p>
                <p><strong>Class:</strong> <?= $subject['class_name'] ?? 'All Classes' ?></p>
                <p><strong>Teacher:</strong> <?= $subject['first_name'] ? $subject['first_name'] . ' ' . $subject['last_name'] : 'Not assigned' ?></p>
                <p><strong>Credit Hours:</strong> <?= $subject['credit_hours'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $subject['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="name" class="form-control" value="<?= $subject['name'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="code" class="form-control" value="<?= $subject['code'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $subject['class_id'] == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>" <?= $subject['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Credit Hours</label>
                        <input type="number" name="credit_hours" class="form-control" value="<?= $subject['credit_hours'] ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_subject" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal<?= $subject['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong><?= $subject['name'] ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_subject" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>