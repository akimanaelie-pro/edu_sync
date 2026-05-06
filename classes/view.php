<?php 
$page = 'classes';
$pageTitle = 'Class Details';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';

$db = db();

$classId = $_GET['id'] ?? null;

if (!$classId) {
    echo '<script>location.href="' . SITE_URL . '/classes/";</script>';
    exit;
}

$class = $db->selectOne("SELECT * FROM classes WHERE id = ?", [$classId]);

if (!$class) {
    echo '<script>location.href="' . SITE_URL . '/classes/";</script>';
    exit;
}

$studentCount = $db->count('students', 'class_id = ?', [$classId]);

$students = $db->select("
    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ?
    ORDER BY u.last_name, u.first_name
", [$classId]);

$subjects = $db->select("
    SELECT sub.*, u.first_name, u.last_name, t.employee_id
    FROM subjects sub
    LEFT JOIN teachers t ON sub.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE sub.class_id = ?
    ORDER BY sub.name
", [$classId]);

$timetable = $db->select("
    SELECT t.*, sub.name as subject_name, u.first_name as teacher_first, u.last_name as teacher_last
    FROM timetable t
    JOIN subjects sub ON t.subject_id = sub.id
    JOIN teachers tea ON t.teacher_id = tea.id
    JOIN users u ON tea.user_id = u.id
    WHERE t.class_id = ?
    ORDER BY FIELD(t.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'), t.start_time
", [$classId]);

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

$attendanceStats = $db->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance
    WHERE class_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$classId]);

$recentGrades = $db->select("
    SELECT g.*, u.first_name, u.last_name, sub.name as subject_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE s.class_id = ?
    ORDER BY g.created_at DESC
    LIMIT 10
", [$classId]);

$avgGrade = $db->selectOne("
    SELECT AVG((score / max_score) * 100) as avg_score
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE s.class_id = ? AND g.academic_year = ? AND g.term = ?
", [$classId, getAcademicYear(), getCurrentTerm()]);

$attendanceRate = $attendanceStats && $attendanceStats['total'] > 0 
    ? round(($attendanceStats['present'] / $attendanceStats['total']) * 100, 1) 
    : 0;
?>
<style>
.nav-tabs .nav-link {
    border: none;
    color: #64748b;
    font-weight: 500;
    padding: 0.75rem 1.25rem;
}
.nav-tabs .nav-link.active {
    color: var(--primary);
    border-bottom: 2px solid var(--primary);
    background: transparent;
}
.nav-tabs .nav-link:hover {
    color: var(--primary);
}
.tab-content .card {
    border: 1px solid #e2e8f0;
    box-shadow: none;
}
.timetable-grid {
    display: grid;
    grid-template-columns: 100px repeat(6, 1fr);
    gap: 1px;
    background: #e2e8f0;
    border-radius: 0.5rem;
    overflow: hidden;
}
.timetable-grid > div {
    background: white;
    padding: 0.75rem;
    min-height: 80px;
}
.timetable-grid .header {
    background: #f8fafc;
    font-weight: 600;
    text-align: center;
    color: #64748b;
    text-transform: capitalize;
}
.timetable-grid .time-slot {
    background: #f8fafc;
    font-weight: 600;
    text-align: center;
    font-size: 0.8rem;
    color: #64748b;
}
.timetable-slot {
    background: linear-gradient(135deg, #4f46e5, #818cf8);
    color: white;
    border-radius: 0.5rem;
    padding: 0.5rem;
    font-size: 0.85rem;
}
.attendance-bar {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-present { background: #10b981; }
.progress-bar-late { background: #f59e0b; }
.progress-bar-absent { background: #ef4444; }
.progress-bar-excused { background: #06b6d4; }
</style>

<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= SITE_URL ?>/classes/" class="btn btn-light">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h4 class="page-title mb-0"><?= $class['name'] ?></h4>
            <small class="text-muted">Grade: <?= $class['grade_level'] ?> | Room: <?= $class['room_number'] ?: 'N/A' ?></small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-user-plus me-1"></i> Add Student
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editClassModal">
            <i class="fas fa-edit me-1"></i> Edit Class
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8);">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= $studentCount ?></h4>
                <small class="text-muted">Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-book"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= count($subjects) ?></h4>
                <small class="text-muted">Subjects</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= $attendanceRate ?>%</h4>
                <small class="text-muted">Attendance</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h4 class="mb-0"><?= $avgGrade ? round($avgGrade['avg_score']) : '-' ?>%</h4>
                <small class="text-muted">Avg Score</small>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#students">
            <i class="fas fa-users me-1"></i> Students (<?= count($students) ?>)
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
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#attendance">
            <i class="fas fa-calendar-check me-1"></i> Attendance
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#grades">
            <i class="fas fa-chart-line me-1"></i> Grades
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="students">
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($students)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                    <h5>No students in this class</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">Add First Student</button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Gender</th>
                                <th>Contact</th>
                                <th>Risk Level</th>
                                <th>XP</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                             class="rounded-circle me-2" width="36">
                                        <div>
                                            <strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= $student['student_id'] ?></code></td>
                                <td><?= ucfirst($student['gender']) ?></td>
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
                                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/students/view.php?id=<?= $student['id'] ?>">
                                                <i class="fas fa-eye me-2"></i>View Profile</a></li>
                                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/students/edit.php?id=<?= $student['id'] ?>">
                                                <i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="removeStudent(<?= $student['id'] ?>, '<?= $student['first_name'] . ' ' . $student['last_name'] ?>')">
                                                <i class="fas fa-user-minus me-2"></i>Remove from Class</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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
                        <a href="<?= SITE_URL ?>/subjects/" class="btn btn-primary">Add Subject</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($subjects as $subject): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?= $subject['name'] ?></h5>
                                <span class="badge bg-secondary"><?= $subject['code'] ?? 'N/A' ?></span>
                            </div>
                            <span class="badge bg-primary"><?= $subject['credit_hours'] ?> cr</span>
                        </div>
                        <p class="text-muted mb-2">
                            <i class="fas fa-chalkboard-teacher me-2"></i>
                            <?= $subject['first_name'] && $subject['last_name'] 
                                ? $subject['first_name'] . ' ' . $subject['last_name'] 
                                : 'Not assigned' ?>
                        </p>
                        <small class="text-muted">
                            <i class="fas fa-id-card me-1"></i> <?= $subject['employee_id'] ?? 'N/A' ?>
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
                    <h5>No timetable entries</h5>
                    <a href="<?= SITE_URL ?>/timetable/" class="btn btn-primary">Manage Timetable</a>
                </div>
                <?php else: ?>
                <div class="timetable-grid">
                    <div class="header">Time</div>
                    <?php foreach ($days as $day): ?>
                    <div class="header"><?= ucfirst($day) ?></div>
                    <?php endforeach; ?>
                    
                    <?php 
                    $timeSlots = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00'];
                    foreach ($timeSlots as $slot): 
                    ?>
                    <div class="time-slot"><?= $slot ?><br><small>-</small></div>
                    <?php foreach ($days as $day): 
                        $slotEntries = array_filter($timetable, function($t) use ($day, $slot) {
                            return $t['day_of_week'] === $day && substr($t['start_time'], 0, 2) === $slot;
                        });
                    ?>
                    <div>
                        <?php if (!empty($slotEntries)): ?>
                            <?php foreach ($slotEntries as $entry): ?>
                            <div class="timetable-slot">
                                <strong><?= $entry['subject_name'] ?></strong><br>
                                <small><?= $entry['teacher_first'] . ' ' . $entry['teacher_last'] ?></small>
                                <?php if ($entry['room']): ?>
                                <br><small><i class="fas fa-map-marker-alt me-1"></i><?= $entry['room'] ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="attendance">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Attendance Overview (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($attendanceStats && $attendanceStats['total'] > 0): ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Overall Attendance Rate</span>
                                <strong class="text-success"><?= $attendanceRate ?>%</strong>
                            </div>
                            <div class="attendance-bar">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar progress-bar-present" style="width: <?= ($attendanceStats['present'] / $attendanceStats['total']) * 100 ?>%"></div>
                                    <div class="progress-bar progress-bar-late" style="width: <?= ($attendanceStats['late'] / $attendanceStats['total']) * 100 ?>%"></div>
                                    <div class="progress-bar progress-bar-absent" style="width: <?= ($attendanceStats['absent'] / $attendanceStats['total']) * 100 ?>%"></div>
                                    <div class="progress-bar progress-bar-excused" style="width: <?= ($attendanceStats['excused'] / $attendanceStats['total']) * 100 ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-3">
                                <h5 class="text-success mb-1"><?= $attendanceStats['present'] ?></h5>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-warning mb-1"><?= $attendanceStats['late'] ?></h5>
                                <small class="text-muted">Late</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-danger mb-1"><?= $attendanceStats['absent'] ?></h5>
                                <small class="text-muted">Absent</small>
                            </div>
                            <div class="col-3">
                                <h5 class="text-info mb-1"><?= $attendanceStats['excused'] ?></h5>
                                <small class="text-muted">Excused</small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <h5>No attendance records</h5>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="<?= SITE_URL ?>/attendance/?class_id=<?= $classId ?>" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-calendar-check me-2"></i>Mark Attendance
                        </a>
                        <a href="<?= SITE_URL ?>/attendance/?class_id=<?= $classId ?>&view=report" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-file-alt me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="grades">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Grades</h5>
                <span class="badge bg-primary"><?= getAcademicYear() ?> - <?= getCurrentTerm() ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentGrades)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5>No grades recorded</h5>
                    <a href="<?= SITE_URL ?>/grades/" class="btn btn-primary">Add Grade</a>
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
                                <td>
                                    <strong><?= $grade['first_name'] . ' ' . $grade['last_name'] ?></strong>
                                </td>
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

<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editClassForm">
                <input type="hidden" name="id" value="<?= $classId ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" class="form-control" value="<?= $class['name'] ?>" required>
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

<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Student to Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Existing Student</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Choose a student...</option>
                            <?php 
                            $unassignedStudents = $db->select("
                                SELECT s.id, u.first_name, u.last_name, s.student_id
                                FROM students s
                                JOIN users u ON s.user_id = u.id
                                WHERE s.class_id IS NULL OR s.class_id = 0
                                ORDER BY u.last_name
                            ");
                            foreach ($unassignedStudents as $stu): ?>
                            <option value="<?= $stu['id'] ?>"><?= $stu['first_name'] . ' ' . $stu['last_name'] ?> (<?= $stu['student_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Students not assigned to any class</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add to Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editClassForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('<?= SITE_URL ?>/api/app.php?action=update_class', {
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

document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('class_id', <?= $classId ?>);
    fetch('<?= SITE_URL ?>/api/app.php?action=assign_student_class', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Student added to class!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});

function removeStudent(studentId, studentName) {
    if (confirm('Remove ' + studentName + ' from this class?')) {
        fetch('<?= SITE_URL ?>/api/app.php?action=remove_student_class&id=' + studentId, {
            method: 'POST'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Student removed from class');
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