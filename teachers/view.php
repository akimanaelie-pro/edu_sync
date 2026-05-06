<?php 
$page = 'teachers';
$pageTitle = 'Teacher Profile';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

$teacherId = $_GET['id'] ?? null;

if (!$teacherId) {
    echo '<script>location.href="' . SITE_URL . '/teachers/";</script>';
    exit;
}

$teacher = $db->selectOne("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$teacherId]);

if (!$teacher) {
    echo '<script>location.href="' . SITE_URL . '/teachers/";</script>';
    exit;
}

$subjects = $db->select("
    SELECT sub.*, c.name as class_name
    FROM subjects sub
    LEFT JOIN classes c ON sub.class_id = c.id
    WHERE sub.teacher_id = ?
    ORDER BY sub.name
", [$teacherId]);

$classCount = $db->count('subjects', 'teacher_id = ?', [$teacherId]);

$studentCount = $db->selectOne("
    SELECT COUNT(DISTINCT s.id) as cnt
    FROM students s
    JOIN subjects sub ON s.class_id = sub.class_id
    WHERE sub.teacher_id = ?
", [$teacherId]);

$timetable = $db->select("
    SELECT t.*, sub.name as subject_name, c.name as class_name
    FROM timetable t
    JOIN subjects sub ON t.subject_id = sub.id
    JOIN classes c ON t.class_id = c.id
    WHERE t.teacher_id = ?
    ORDER BY FIELD(t.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'), t.start_time
", [$teacherId]);

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

$recentGrades = $db->select("
    SELECT g.*, u.first_name, u.last_name, sub.name as subject_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE sub.teacher_id = ? AND g.recorded_by = ?
    ORDER BY g.created_at DESC
    LIMIT 10
", [$teacherId, $teacher['user_id']]);

require_once __DIR__ . '/../config/header.php';
?>
<style>
.profile-header {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}
.stat-card-teacher {
    background: white;
    border-radius: 0.75rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= SITE_URL ?>/teachers/" class="btn btn-light">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h4 class="page-title mb-0">Teacher Profile</h4>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal">
        <i class="fas fa-edit me-1"></i> Edit Profile
    </button>
</div>

<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-auto">
            <?php if ($teacher['profile_image']): ?>
            <img src="<?= SITE_URL ?>/<?= $teacher['profile_image'] ?>" 
                 class="rounded-circle" width="100" height="100">
            <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($teacher['first_name'] . ' ' . $teacher['last_name']) ?>&background=fff&color=10b981&size=128" 
                 class="rounded-circle" width="100" height="100">
            <?php endif; ?>
        </div>
        <div class="col">
            <h3 class="mb-1"><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></h3>
            <p class="mb-1 opacity-75"><?= $teacher['employee_id'] ?></p>
            <p class="mb-1"><i class="fas fa-briefcase me-2"></i><?= $teacher['department'] ?: 'No Department' ?></p>
            <p class="mb-0"><i class="fas fa-graduation-cap me-2"></i><?= $teacher['qualification'] ?: 'No Qualification' ?></p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card-teacher">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-book"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= $classCount ?></h4>
                <small class="text-muted">Subjects</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-teacher">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8);">
                <i class="fas fa-door-open"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= $studentCount['cnt'] ?? 0 ?></h4>
                <small class="text-muted">Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-teacher">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= count($timetable) ?></h4>
                <small class="text-muted">Schedule Slots</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-teacher">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= date('Y', strtotime($teacher['hire_date'])) ?: '-' ?></h4>
                <small class="text-muted">Joined</small>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info">
            <i class="fas fa-user me-1"></i> Information
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#subjects">
            <i class="fas fa-book me-1"></i> Subjects (<?= count($subjects) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#timetable">
            <i class="fas fa-clock me-1"></i> Timetable
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#grades">
            <i class="fas fa-chart-line me-1"></i> Recent Grades
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="info">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 150px;">First Name</td>
                                <td><strong><?= $teacher['first_name'] ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Last Name</td>
                                <td><strong><?= $teacher['last_name'] ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Email</td>
                                <td><?= $teacher['email'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Phone</td>
                                <td><?= $teacher['phone'] ?: '-' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Employee ID</td>
                                <td><code><?= $teacher['employee_id'] ?></code></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Professional Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 150px;">Department</td>
                                <td><strong><?= $teacher['department'] ?: '-' ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Qualification</td>
                                <td><?= $teacher['qualification'] ?: '-' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Hire Date</td>
                                <td><?= $teacher['hire_date'] ? formatDate($teacher['hire_date']) : '-' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Salary</td>
                                <td><?= $teacher['salary'] ? '$' . number_format($teacher['salary'], 2) : '-' ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="subjects">
        <div class="row g-3">
            <?php if (empty($subjects)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5>No subjects assigned</h5>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($subjects as $subject): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-2"><?= $subject['name'] ?></h5>
                        <span class="badge bg-secondary mb-2"><?= $subject['code'] ?? 'N/A' ?></span>
                        <p class="text-muted mb-1">
                            <i class="fas fa-door-open me-2"></i><?= $subject['class_name'] ?? 'Not assigned' ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i><?= $subject['credit_hours'] ?> credit hours
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="timetable">
        <div class="card">
            <div class="card-body">
                <?php if (empty($timetable)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                    <h5>No schedule entries</h5>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable as $slot): ?>
                            <tr>
                                <td class="text-capitalize"><strong><?= $slot['day_of_week'] ?></strong></td>
                                <td><?= substr($slot['start_time'], 0, 5) ?> - <?= substr($slot['end_time'], 0, 5) ?></td>
                                <td><?= $slot['subject_name'] ?></td>
                                <td><?= $slot['class_name'] ?></td>
                                <td><?= $slot['room'] ?: '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="grades">
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($recentGrades)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5>No grades recorded yet</h5>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentGrades as $grade): ?>
                            <tr>
                                <td><strong><?= $grade['first_name'] . ' ' . $grade['last_name'] ?></strong></td>
                                <td><?= $grade['subject_name'] ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($grade['assessment_type']) ?></span></td>
                                <td><?= $grade['score'] ?>/<?= $grade['max_score'] ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        in_array($grade['grade_letter'], ['A+', 'A', 'A-', 'B+']) ? 'success' : 
                                        (in_array($grade['grade_letter'], ['B', 'B-', 'C+']) ? 'warning' : 'danger')
                                    ?>">
                                        <?= $grade['grade_letter'] ?>
                                    </span>
                                </td>
                                <td><small><?= formatDate($grade['created_at']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTeacherForm">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php if ($teacher['profile_image']): ?>
                            <img src="<?= SITE_URL ?>/<?= $teacher['profile_image'] ?>" 
                                 id="previewImg" class="rounded-circle" width="100" height="100">
                            <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($teacher['first_name'] . ' ' . $teacher['last_name']) ?>&background=10b981&color=fff" 
                                 id="previewImg" class="rounded-circle" width="100" height="100">
                            <?php endif; ?>
                            <label for="profileImg" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor:pointer;">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="profileImg" class="d-none" name="profile_image" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <small class="text-muted d-block mt-2">Click camera icon to upload photo</small>
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
                </div>
                <div class="modal-footer">
                    <button type="button" id="updateTeacherBtn" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('updateTeacherBtn').addEventListener('click', function() {
    const form = document.getElementById('editTeacherForm');
    const formData = new FormData(form);
    formData.append('id', <?= $teacherId ?>);
    
    fetch('<?= SITE_URL ?>/api/app.php?action=update_teacher', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});
</script>