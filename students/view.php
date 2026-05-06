<?php 
$page = 'students';
$pageTitle = 'Student Profile';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    echo '<script>location.href="' . SITE_URL . '/students/";</script>';
    exit;
}

$student = $db->selectOne("
    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", [$studentId]);

if (!$student) {
    echo '<script>location.href="' . SITE_URL . '/students/";</script>';
    exit;
}

$tags = json_decode($student['tags'] ?? '[]', true);
$classes = $db->select("SELECT * FROM classes ORDER BY name");

$subjects = $db->select("
    SELECT sub.*, u.first_name as teacher_first, u.last_name as teacher_last
    FROM subjects sub
    LEFT JOIN teachers tea ON sub.teacher_id = tea.id
    LEFT JOIN users u ON tea.user_id = u.id
    WHERE sub.class_id = ?
    ORDER BY sub.name
", [$student['class_id'] ?? 0]);

$attendanceStats = $db->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance
    WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$studentId]);

$recentGrades = $db->select("
    SELECT g.*, sub.name as subject_name
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ?
    ORDER BY g.created_at DESC
    LIMIT 10
", [$studentId]);

$avgGrade = $db->selectOne("
    SELECT AVG((score / max_score) * 100) as avg_score
    FROM grades
    WHERE student_id = ? AND academic_year = ? AND term = ?
", [$studentId, getAcademicYear(), getCurrentTerm()]);

$attendanceRate = $attendanceStats && $attendanceStats['total'] > 0 
    ? round(($attendanceStats['present'] / $attendanceStats['total']) * 100, 1) 
    : 0;

require_once __DIR__ . '/../config/header.php';
?>
<style>
.profile-header {
    background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}
.stat-card-student {
    background: white;
    border-radius: 0.75rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

@media print {
    body { background: white !important; }
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 15mm;
        background: white;
        font-size: 11px;
    }
    .page-header, .nav-tabs, .tab-content, .modal { display: none !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; margin-bottom: 8px !important; padding: 10px; page-break-inside: avoid; }
    .card-header { background: #f8f9fa !important; padding: 5px 10px !important; }
    .card-header h5 { font-size: 12px; margin: 0; }
    .table { margin-bottom: 0 !important; }
    .table td, .table th { padding: 4px 6px !important; font-size: 10px; }
    .stat-card-student { padding: 8px !important; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .stat-card-student .stat-icon { width: 30px !important; height: 30px !important; font-size: 12px !important; border-radius: 5px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0; }
    .stat-card-student h4 { font-size: 14px; margin: 0; }
    .stat-card-student small { font-size: 9px; }
    .profile-header { background: #4f46e5 !important; padding: 15px !important; margin-bottom: 10px !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .profile-header h3 { font-size: 18px; margin-bottom: 5px; }
    .profile-header p { font-size: 11px; margin-bottom: 3px; }
    .profile-header img { width: 70px !important; height: 70px !important; object-fit: cover !important; border: 3px solid white !important; margin-right: 10px; }
    .row { margin: 0 !important; }
    .col-md-3, .col-md-6 { padding: 3px !important; }
    .mt-2 { margin-top: 8px !important; }
    @page { size: A4; margin: 8mm; }
}
</style>

<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= SITE_URL ?>/students/" class="btn btn-light"><i class="fas fa-arrow-left"></i></a>
        <h4 class="page-title mb-0">Student Profile</h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal"><i class="fas fa-edit me-1"></i> Edit Profile</button>
    </div>
</div>

<div id="printArea">
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if ($student['profile_image']): ?>
                <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" class="rounded-circle" width="100" height="100" style="object-fit: cover; border: 3px solid white;">
                <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=fff&color=4f46e5&size=128" class="rounded-circle" width="100" height="100" style="border: 3px solid white;">
                <?php endif; ?>
            </div>
            <div class="col">
                <h3 class="mb-1"><?= $student['first_name'] . ' ' . $student['last_name'] ?></h3>
                <p class="mb-1" style="opacity:0.9;"><i class="fas fa-id-card me-2"></i><?= $student['student_id'] ?></p>
                <p class="mb-1" style="opacity:0.9;"><i class="fas fa-door-open me-2"></i><?= $student['class_name'] ?? 'Not assigned' ?></p>
                <p class="mb-0">
                    <?php foreach ($tags as $tag): ?>
                    <span class="badge bg-warning me-1"><?= str_replace('_', ' ', ucfirst($tag)) ?></span>
                    <?php endforeach; ?>
                </p>
            </div>
            <div class="col-auto text-end">
                <small style="opacity:0.8;">Academic Year: <?= getAcademicYear() ?></small><br>
                <small style="opacity:0.8;">Term: <?= getCurrentTerm() ?></small>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card-student">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8);"><i class="fas fa-book"></i></div>
                <div><h4 class="mb-0"><?= count($subjects) ?></h4><small class="text-muted">Subjects</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-student">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fas fa-calendar-check"></i></div>
                <div><h4 class="mb-0"><?= $attendanceRate ?>%</h4><small class="text-muted">Attendance</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-student">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="fas fa-chart-line"></i></div>
                <div><h4 class="mb-0"><?= $avgGrade ? round($avgGrade['avg_score']) : '-' ?>%</h4><small class="text-muted">Avg Score</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-student">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);"><i class="fas fa-star"></i></div>
                <div><h4 class="mb-0"><?= $student['xp_points'] ?></h4><small class="text-muted">XP Points</small></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Personal Information</h5></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted" style="width:120px;">First Name</td><td><strong><?= $student['first_name'] ?></strong></td></tr>
                        <tr><td class="text-muted">Last Name</td><td><strong><?= $student['last_name'] ?></strong></td></tr>
                        <tr><td class="text-muted">Email</td><td><?= $student['email'] ?></td></tr>
                        <tr><td class="text-muted">Phone</td><td><?= $student['phone'] ?: '-' ?></td></tr>
                        <tr><td class="text-muted">Gender</td><td><?= ucfirst($student['gender']) ?></td></tr>
                        <tr><td class="text-muted">Date of Birth</td><td><?= $student['date_of_birth'] ? formatDate($student['date_of_birth']) : '-' ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Academic Information</h5></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted" style="width:120px;">Student ID</td><td><code><?= $student['student_id'] ?></code></td></tr>
                        <tr><td class="text-muted">Class</td><td><?= $student['class_name'] ?? 'Not assigned' ?></td></tr>
                        <tr><td class="text-muted">Admission Date</td><td><?= $student['admission_date'] ? formatDate($student['admission_date']) : '-' ?></td></tr>
                        <tr><td class="text-muted">Risk Level</td><td><span class="badge bg-<?= $student['risk_level'] === 'high' ? 'danger' : ($student['risk_level'] === 'medium' ? 'warning' : 'success') ?>"><?= ucfirst($student['risk_level']) ?></span></td></tr>
                        <tr><td class="text-muted">Blood Group</td><td><?= $student['blood_group'] ?: '-' ?></td></tr>
                        <tr><td class="text-muted">Nationality</td><td><?= $student['nationality'] ?: '-' ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Recent Grades</h5></div>
                <div class="card-body p-0">
                    <?php if (empty($recentGrades)): ?>
                    <div class="text-center py-2 text-muted">No grades recorded</div>
                    <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Subject</th><th>Type</th><th>Score</th><th>Grade</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentGrades as $grade): ?>
                            <tr>
                                <td><?= $grade['subject_name'] ?></td>
                                <td><?= ucfirst($grade['assessment_type']) ?></td>
                                <td><?= $grade['score'] ?>/<?= $grade['max_score'] ?></td>
                                <td><span class="badge bg-<?= in_array($grade['grade_letter'], ['A+', 'A', 'A-', 'B+']) ? 'success' : (in_array($grade['grade_letter'], ['B', 'B-', 'C+']) ? 'warning' : 'danger') ?>"><?= $grade['grade_letter'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Subjects</h5></div>
                <div class="card-body">
                    <?php if (empty($subjects)): ?>
                    <div class="text-center text-muted">No subjects assigned</div>
                    <?php else: ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($subjects as $subject): ?>
                        <span class="badge bg-secondary"><?= $subject['name'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 text-center text-muted small">
        <p class="mb-0">Printed on: <?= date('F j, Y') ?> | <?= SITE_NAME ?></p>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php if ($student['profile_image']): ?>
                            <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" id="previewImg" class="rounded-circle" width="100" height="100" style="object-fit: cover;">
                            <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" id="previewImg" class="rounded-circle" width="100" height="100">
                            <?php endif; ?>
                            <label for="profileImg" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor:pointer;"><i class="fas fa-camera"></i></label>
                            <input type="file" id="profileImg" class="d-none" name="profile_image" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <small class="text-muted d-block mt-2">Click camera to upload photo</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" value="<?= $student['first_name'] ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" value="<?= $student['last_name'] ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $student['email'] ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= $student['phone'] ?? '' ?>"></div>
                        <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="<?= $student['date_of_birth'] ?? '' ?>"></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="male" <?= $student['gender'] === 'male' ? 'selected' : '' ?>>Male</option><option value="female" <?= $student['gender'] === 'female' ? 'selected' : '' ?>>Female</option><option value="other" <?= $student['gender'] === 'other' ? 'selected' : '' ?>>Other</option></select></div>
                        <div class="col-md-6"><label class="form-label">Class</label><select name="class_id" class="form-select"><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= $student['class_id'] == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Risk Level</label><select name="risk_level" class="form-select"><option value="low" <?= $student['risk_level'] === 'low' ? 'selected' : '' ?>>Low</option><option value="medium" <?= $student['risk_level'] === 'medium' ? 'selected' : '' ?>>Medium</option><option value="high" <?= $student['risk_level'] === 'high' ? 'selected' : '' ?>>High</option></select></div>
                        <div class="col-12"><label class="form-label">Smart Tags</label><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="tag_high_performer" value="high_performer" id="tag1" <?= in_array('high_performer', $tags) ? 'checked' : '' ?>><label class="form-check-label" for="tag1">High Performer</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="tag_at_risk" value="at_risk" id="tag2" <?= in_array('at_risk', $tags) ? 'checked' : '' ?>><label class="form-check-label" for="tag2">At Risk</label></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" id="updateStudentBtn" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { document.getElementById('previewImg').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('updateStudentBtn').addEventListener('click', function() {
    const form = document.getElementById('editStudentForm');
    const formData = new FormData(form);
    formData.append('id', <?= $studentId ?>);
    
    fetch('<?= SITE_URL ?>/api/app.php?action=update_student', { method: 'POST', body: formData })
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