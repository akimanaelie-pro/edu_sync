<?php 
$page = 'classes';
$pageTitle = 'Classes & Sections';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    $db = db();
    
    if (isset($_POST['edit_id'])) {
        $db->update('classes', [
            'name' => $_POST['name'],
            'grade_level' => $_POST['grade_level'] ?? '',
            'capacity' => (int)($_POST['capacity'] ?? 40),
            'room_number' => $_POST['room_number'] ?? ''
        ], 'id = :id', ['id' => (int)$_POST['edit_id']]);
        echo json_encode(['success' => true, 'message' => 'Class updated!']);
    } else if (isset($_POST['name'])) {
        $db->insert('classes', [
            'uuid' => generateUUID(),
            'name' => $_POST['name'],
            'grade_level' => $_POST['grade_level'] ?? '',
            'capacity' => (int)($_POST['capacity'] ?? 40),
            'room_number' => $_POST['room_number'] ?? '',
            'academic_year' => getAcademicYear(),
            'sync_status' => 'pending'
        ]);
        echo json_encode(['success' => true, 'message' => 'Class created!']);
    }
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';
$db = db();

$classes = $db->select("SELECT * FROM classes ORDER BY name");

foreach ($classes as &$cls) {
    $cls['student_count'] = $db->count('students', 'class_id = ?', [$cls['id']]);
}
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-door-open me-2"></i>Classes & Sections</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> Add Class
    </button>
</div>

<div class="row g-3">
    <?php foreach ($classes as $class): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= $class['name'] ?></h5>
                <span class="badge bg-primary"><?= $class['grade_level'] ?></span>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="mb-0 text-primary"><?= $class['student_count'] ?></h4>
                        <small class="text-muted">Students</small>
                    </div>
                    <div class="col-4">
                        <h4 class="mb-0"><?= $class['capacity'] ?></h4>
                        <small class="text-muted">Capacity</small>
                    </div>
                    <div class="col-4">
                        <h4 class="mb-0"><?= $class['room_number'] ?: '-' ?></h4>
                        <small class="text-muted">Room</small>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="view.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-light">View</a>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editModal<?= $class['id'] ?>">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteClass(<?= $class['id'] ?>, '<?= $class['name'] ?>')">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($classes)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-door-open fa-3x text-muted mb-3"></i>
                <h5>No classes yet</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">Add First Class</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createClassForm" action="index.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" id="className" class="form-control" placeholder="e.g., Senior One" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <input type="text" name="grade_level" id="classGrade" class="form-control" placeholder="e.g., S.1">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" id="classCapacity" class="form-control" value="40">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" id="classRoom" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($classes as $class): ?>
<div class="modal fade" id="editModal<?= $class['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="editClassForm" method="POST">
                <input type="hidden" name="edit_id" value="<?= $class['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" class="form-control" value="<?= $class['name'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <input type="text" name="grade_level" class="form-control" value="<?= $class['grade_level'] ?>">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="<?= $class['capacity'] ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" class="form-control" value="<?= $class['room_number'] ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.getElementById('createClassForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?= SITE_URL ?>/classes/', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Class created!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});

document.querySelectorAll('.editClassForm').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('<?= SITE_URL ?>/classes/', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Class updated!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Error', 'error');
            }
        })
        .catch(e => showToast('Error: ' + e.message, 'error'));
    });
});

function deleteClass(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?')) {
        fetch('<?= SITE_URL ?>/api/app.php?action=delete_class&id=' + id, {
            method: 'POST'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Class deleted!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Error', 'error');
            }
        })
        .catch(e => showToast('Error: ' + e.message, 'error'));
    }
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>