<?php 
$page = 'teachers';
$pageTitle = 'Teachers';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_teacher'])) {
    header('Content-Type: application/json');
    
    if ($db->exists('users', 'email = ?', [$_POST['email']])) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    $uuid = generateUUID();
    $passwordHash = password_hash($_POST['password'] ?? 'teacher123', PASSWORD_DEFAULT);
    
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = 'teacher_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
            $profileImagePath = 'uploads/profiles/' . $filename;
        }
    }
    
    $userData = [
        'uuid' => $uuid,
        'email' => $_POST['email'],
        'password_hash' => $passwordHash,
        'role' => 'teacher',
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'phone' => $_POST['phone'] ?? null,
        'sync_status' => 'pending'
    ];
    
    if ($profileImagePath) {
        $userData['profile_image'] = $profileImagePath;
    }
    
    $userId = $db->insert('users', $userData);
    
    if ($userId) {
        $db->insert('teachers', [
            'uuid' => generateUUID(),
            'user_id' => $userId,
            'employee_id' => 'EMP' . date('Y') . str_pad($db->count('teachers', '1=1') + 1, 4, '0', STR_PAD_LEFT),
            'department' => $_POST['department'] ?? '',
            'qualification' => $_POST['qualification'] ?? '',
            'hire_date' => $_POST['hire_date'] ?? date('Y-m-d'),
            'sync_status' => 'pending'
        ]);
        echo json_encode(['success' => true, 'message' => 'Teacher created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create teacher']);
    }
    exit;
}

require_once __DIR__ . '/../config/header.php';

$teachers = $db->select("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.first_name
");
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-chalkboard-teacher me-2"></i>Teachers</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> Add Teacher
    </button>
</div>

<div class="row g-3">
    <?php foreach ($teachers as $teacher): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php if ($teacher['profile_image']): ?>
                <img src="<?= SITE_URL ?>/<?= $teacher['profile_image'] ?>" 
                     class="rounded-circle mb-3" width="80" height="80" style="object-fit: cover;">
                <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($teacher['first_name'] . ' ' . $teacher['last_name']) ?>&background=10b981&color=fff" 
                     class="rounded-circle mb-3" width="80" height="80">
                <?php endif; ?>
                <h5><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></h5>
                <p class="text-muted mb-1"><?= $teacher['employee_id'] ?></p>
                <p class="text-muted mb-1"><?= $teacher['department'] ?: 'No Department' ?></p>
                <p class="text-muted small"><?= $teacher['email'] ?></p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="view.php?id=<?= $teacher['id'] ?>" class="btn btn-sm btn-light">Profile</a>
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editModal<?= $teacher['id'] ?>">Edit</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($teachers)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                <h5>No teachers yet</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">Add First Teacher</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createTeacherForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img src="https://ui-avatars.com/api/?name=New+Teacher&background=10b981&color=fff" 
                                 id="createPreviewImg" class="rounded-circle" width="100" height="100">
                            <label for="createProfileImg" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor:pointer;">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="createProfileImg" class="d-none" name="profile_image" accept="image/*" onchange="previewCreateImage(this)">
                        </div>
                        <small class="text-muted d-block mt-2">Click camera to upload photo</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="password" class="form-control" value="teacher123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="submitTeacherBtn" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($teachers as $teacher): ?>
<div class="modal fade" id="editModal<?= $teacher['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTeacherForm<?= $teacher['id'] ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php if ($teacher['profile_image']): ?>
                            <img src="<?= SITE_URL ?>/<?= $teacher['profile_image'] ?>" 
                                 id="previewImg<?= $teacher['id'] ?>" 
                                 class="rounded-circle" width="100" height="100" style="object-fit: cover;">
                            <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($teacher['first_name'] . ' ' . $teacher['last_name']) ?>&background=10b981&color=fff" 
                                 id="previewImg<?= $teacher['id'] ?>" 
                                 class="rounded-circle" width="100" height="100">
                            <?php endif; ?>
                            <label for="profileImg<?= $teacher['id'] ?>" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor:pointer;">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="profileImg<?= $teacher['id'] ?>" class="d-none" name="profile_image" accept="image/*" onchange="previewImage(this, 'previewImg<?= $teacher['id'] ?>')">
                        </div>
                        <small class="text-muted d-block mt-2">Click camera to upload photo</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= $teacher['first_name'] ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= $teacher['last_name'] ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= $teacher['email'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= $teacher['phone'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control" value="<?= $teacher['department'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="<?= $teacher['qualification'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" class="form-control" value="<?= $teacher['hire_date'] ?? '' ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary updateTeacherBtn" data-id="<?= $teacher['id'] ?>">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewCreateImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('createPreviewImg').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('submitTeacherBtn').addEventListener('click', function() {
    const form = document.getElementById('createTeacherForm');
    const formData = new FormData(form);
    formData.append('create_teacher', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});

document.querySelectorAll('.updateTeacherBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        const teacherId = this.dataset.id;
        const form = document.getElementById('editTeacherForm' + teacherId);
        const formData = new FormData(form);
        formData.append('id', teacherId);
        
        fetch('<?= SITE_URL ?>/api/app.php?action=update_teacher', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Error', 'error');
            }
        })
        .catch(e => showToast('Error: ' + e.message, 'error'));
    });
});
</script>