<?php 
$page = 'students';
$pageTitle = 'Students';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';

$db = db();

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$guardians = $db->select("SELECT * FROM guardians ORDER BY first_name");

$search = $_GET['search'] ?? '';
$classFilter = $_GET['class_id'] ?? '';
$tagFilter = $_GET['tag'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($classFilter) {
    $where .= " AND s.class_id = ?";
    $params[] = $classFilter;
}

$students = $db->select("
    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE $where
    ORDER BY c.name, u.first_name, u.last_name
    LIMIT 100
", $params);

// Group students by class
$studentsByClass = [];
$noClassStudents = [];
foreach ($students as $student) {
    if ($student['class_id'] && $student['class_name']) {
        $studentsByClass[$student['class_id']][] = $student;
    } else {
        $noClassStudents[] = $student;
    }
}
?>

<div class="page-header">
    <h4 class="page-title"><i class="fas fa-user-graduate me-2"></i>Student Information System</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-import me-1"></i> Import
        </button>
        <button class="btn btn-outline-secondary" onclick="exportStudents()">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-1"></i> Add Student
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name or ID..." value="<?= $search ?>">
            </div>
            <div class="col-md-3">
                <select name="class_id" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= $classFilter == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="tag" class="form-select">
                    <option value="">All Tags</option>
                    <option value="high_performer" <?= $tagFilter === 'high_performer' ? 'selected' : '' ?>>High Performer</option>
                    <option value="at_risk" <?= $tagFilter === 'at_risk' ? 'selected' : '' ?>>At Risk</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($classFilter && isset($studentsByClass[$classFilter])): ?>
<div class="card mb-4" style="border-left: 4px solid #6366f1;">
    <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
        <h5 class="mb-0" style="color: #4f46e5;">
            <i class="fas fa-users me-2"></i><?= $studentsByClass[$classFilter][0]['class_name'] ?>
            <span class="badge bg-primary ms-2"><?= count($studentsByClass[$classFilter]) ?> students</span>
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success" onclick="printClassList(<?= $classFilter ?>, '<?= addslashes($studentsByClass[$classFilter][0]['class_name']) ?>')">
                <i class="fas fa-print me-1"></i> Print List
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal" 
                    onclick="document.querySelector('#addStudentModal [name=class_id]').value='<?= $classFilter ?>'">
                <i class="fas fa-plus me-1"></i> Add Student
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Risk Level</th>
                        <th>XP Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentsByClass[$classFilter] as $student): 
                        $tags = json_decode($student['tags'] ?? '[]', true);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($student['profile_image']): ?>
                                <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" 
                                     class="rounded-circle me-2" width="36" height="36" style="object-fit: cover;">
                                <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                     class="rounded-circle me-2" width="36">
                                <?php endif; ?>
                                <div>
                                    <strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                    <br><small class="text-muted"><?= $student['email'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?= $student['student_id'] ?></code></td>
                        <td><?= $student['phone'] ?? '-' ?></td>
                        <td>
                            <span class="badge bg-<?= $student['risk_level'] === 'high' ? 'danger' : ($student['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                <?= ucfirst($student['risk_level']) ?>
                            </span>
                        </td>
                        <td><span class="text-primary"><i class="fas fa-star me-1"></i><?= $student['xp_points'] ?></span></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="view.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-eye me-2"></i>View Profile</a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteStudent(<?= $student['id'] ?>, '<?= addslashes($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                        <i class="fas fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif (!$classFilter): ?>
<?php foreach ($studentsByClass as $classId => $classStudents): ?>
<div class="card mb-4" style="border-left: 4px solid #6366f1;">
    <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
        <h5 class="mb-0" style="color: #4f46e5;">
            <i class="fas fa-users me-2"></i><?= $classStudents[0]['class_name'] ?>
            <span class="badge bg-primary ms-2"><?= count($classStudents) ?> students</span>
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success" onclick="printClassList(<?= $classId ?>, '<?= addslashes($classStudents[0]['class_name']) ?>')">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal" 
                    onclick="document.querySelector('#addStudentModal [name=class_id]').value='<?= $classId ?>'">
                <i class="fas fa-plus me-1"></i> Add Student
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Risk Level</th>
                        <th>XP Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classStudents as $student): 
                        $tags = json_decode($student['tags'] ?? '[]', true);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($student['profile_image']): ?>
                                <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" 
                                     class="rounded-circle me-2" width="36" height="36" style="object-fit: cover;">
                                <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                     class="rounded-circle me-2" width="36">
                                <?php endif; ?>
                                <div>
                                    <strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                    <br><small class="text-muted"><?= $student['email'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?= $student['student_id'] ?></code></td>
                        <td><?= $student['phone'] ?? '-' ?></td>
                        <td>
                            <span class="badge bg-<?= $student['risk_level'] === 'high' ? 'danger' : ($student['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                <?= ucfirst($student['risk_level']) ?>
                            </span>
                        </td>
                        <td><span class="text-primary"><i class="fas fa-star me-1"></i><?= $student['xp_points'] ?></span></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="view.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-eye me-2"></i>View Profile</a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteStudent(<?= $student['id'] ?>, '<?= addslashes($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                        <i class="fas fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (!empty($noClassStudents)): ?>
<div class="card mb-4" style="border-left: 4px solid #f59e0b;">
    <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(251,191,36,0.05));">
        <h5 class="mb-0" style="color: #d97706;">
            <i class="fas fa-exclamation-circle me-2"></i>Unassigned Students
            <span class="badge bg-warning ms-2"><?= count($noClassStudents) ?> students</span>
        </h5>
        <button class="btn btn-sm btn-success" onclick="printUnassigned()">
            <i class="fas fa-print me-1"></i> Print List
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Risk Level</th>
                        <th>XP Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($noClassStudents as $student): 
                        $tags = json_decode($student['tags'] ?? '[]', true);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($student['profile_image']): ?>
                                <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" 
                                     class="rounded-circle me-2" width="36" height="36" style="object-fit: cover;">
                                <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                     class="rounded-circle me-2" width="36">
                                <?php endif; ?>
                                <div>
                                    <strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                    <br><small class="text-muted"><?= $student['email'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?= $student['student_id'] ?></code></td>
                        <td><?= $student['phone'] ?? '-' ?></td>
                        <td>
                            <span class="badge bg-<?= $student['risk_level'] === 'high' ? 'danger' : ($student['risk_level'] === 'medium' ? 'warning' : 'success') ?>">
                                <?= ucfirst($student['risk_level']) ?>
                            </span>
                        </td>
                        <td><span class="text-primary"><i class="fas fa-star me-1"></i><?= $student['xp_points'] ?></span></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="view.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-eye me-2"></i>View Profile</a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?= $student['id'] ?>">
                                        <i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteStudent(<?= $student['id'] ?>, '<?= addslashes($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                        <i class="fas fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (empty($students) && !$search && !$tagFilter): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No students found</h5>
        <p class="text-muted">Click "Add Student" to get started</p>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 2px solid rgba(99,102,241,0.1);">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color: #6366f1;"></i>Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img src="https://ui-avatars.com/api/?name=New+Student&background=4f46e5&color=fff" 
                                 id="studentPreviewImg" class="rounded-circle" width="100" height="100">
                            <label for="studentProfileImg" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="studentProfileImg" class="d-none" name="profile_image" accept="image/*" onchange="previewStudentImage(this)">
                        </div>
                        <small class="text-muted d-block mt-2">Click camera to upload photo</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color: #6366f1; font-weight: 500;">First Name</label>
                            <input type="text" name="first_name" class="form-control" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #06b6d4; font-weight: 500;">Last Name</label>
                            <input type="text" name="last_name" class="form-control" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Email</label>
                            <input type="email" name="email" class="form-control" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #f59e0b; font-weight: 500;">Phone</label>
                            <input type="text" name="phone" class="form-control" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #10b981; font-weight: 500;">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" style="border-color: rgba(16,185,129,0.3); border-radius: 10px;" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #ef4444; font-weight: 500;">Gender</label>
                            <select name="gender" class="form-select" style="border-color: rgba(239,68,68,0.3); border-radius: 10px;" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #6366f1; font-weight: 500;">Admission Date</label>
                            <input type="date" name="admission_date" class="form-control" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #06b6d4; font-weight: 500;">Class</label>
                            <select name="class_id" class="form-select" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>"><?= $class['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Blood Group</label>
                            <select name="blood_group" class="form-select" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;">
                                <option value="">Select</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #f59e0b; font-weight: 500;">Nationality</label>
                            <input type="text" name="nationality" class="form-control" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" style="border-radius: 10px;"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Smart Tags</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tag_high_performer" value="high_performer" id="tag1">
                                <label class="form-check-label" for="tag1">High Performer</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tag_at_risk" value="at_risk" id="tag2">
                                <label class="form-check-label" for="tag2">At Risk</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #10b981; font-weight: 500;">Risk Level</label>
                            <select name="risk_level" class="form-select" style="border-color: rgba(16,185,129,0.3); border-radius: 10px;">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #ef4444; font-weight: 500;">Password</label>
                            <input type="text" name="password" class="form-control" style="border-color: rgba(239,68,68,0.3); border-radius: 10px;" value="student123">
                            <small class="text-muted">Default password</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: rgba(99,102,241,0.02);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveStudentBtn" class="btn btn-primary" style="border-radius: 10px;">
                        <i class="fas fa-save me-1"></i> Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title">Import Students (CSV/Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Upload a CSV file with columns: First Name, Last Name, Email, Phone, Date of Birth, Gender, Class
                </div>
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewStudentImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('studentPreviewImg').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('saveStudentBtn').addEventListener('click', function() {
    const form = document.getElementById('addStudentForm');
    const formData = new FormData(form);
    
    fetch('<?= SITE_URL ?>/api/app.php?action=create_student', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});

function exportStudents() {
    window.location.href = 'export.php';
}

function printClassList(classId, className) {
    const url = `print_class.php?class_id=${classId}&class_name=${encodeURIComponent(className)}`;
    window.open(url, '_blank', 'width=800,height=600');
}

function printUnassigned() {
    window.open('print_class.php?class_id=0&class_name=Unassigned Students', '_blank', 'width=800,height=600');
}

function deleteStudent(studentId, studentName) {
    if (confirm('Are you sure you want to delete ' + studentName + '?')) {
        fetch('<?= SITE_URL ?>/api/app.php?action=delete_student&id=' + studentId, {
            method: 'POST'
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
    }
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
