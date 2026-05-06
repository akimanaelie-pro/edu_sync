<?php
$page = 'assignments';
$pageTitle = 'Assignments';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$userRole = getUserRole();
$userId = getUserId();

// Get filters
$selectedTeacher = $_GET['teacher_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';
$selectedClass = $_GET['class_id'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

$where = "1=1";
$params = [];

if ($selectedTeacher) {
    $where .= " AND a.created_by = ?";
    $params[] = $selectedTeacher;
}

if ($selectedSubject) {
    $where .= " AND a.subject_id = ?";
    $params[] = $selectedSubject;
}

if ($selectedClass) {
    $where .= " AND (a.subject_id IN (SELECT id FROM subjects WHERE class_id = ?) OR ac.class_id = ?)";
    $params[] = $selectedClass;
    $params[] = $selectedClass;
}

if ($selectedType) {
    $where .= " AND a.assignment_type = ?";
    $params[] = $selectedType;
}

if ($selectedStatus === 'active') {
    $where .= " AND a.due_date > NOW()";
} elseif ($selectedStatus === 'expired') {
    $where .= " AND a.due_date <= NOW()";
} elseif ($selectedStatus === 'pending_grading') {
    $where .= " AND EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.marks_obtained IS NULL)";
}

// Get all assignments with teacher info, subject, and submission stats
$assignments = $db->select("
    SELECT a.*, 
           u.first_name, u.last_name,
           s.name as subject_name, s.subject_code,
           c.name as class_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND marks_obtained IS NULL AND status != 'resubmit') as pending_grading,
           (SELECT COUNT(DISTINCT class_id) FROM assignment_classes ac WHERE ac.assignment_id = a.id) as class_count
    FROM assignments a
    JOIN users u ON a.created_by = u.id
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN assignment_classes ac ON a.id = ac.assignment_id
    WHERE $where
    GROUP BY a.id
    ORDER BY a.created_at DESC
", $params);

// Get filter data
$teachers = $db->select("
    SELECT DISTINCT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN assignments a ON u.id = a.created_by 
    ORDER BY u.first_name
");

$subjects = $db->select("SELECT * FROM subjects ORDER BY name");
$classes = $db->select("SELECT * FROM classes ORDER BY name");

require_once __DIR__ . '/../config/header.php';
?>

<div class="page-header">
    <div>
        <h4 class="page-title">Assignments</h4>
        <p class="text-muted mb-0">View all teacher-assigned assignments</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <button class="btn btn-primary" onclick="exportData()">
            <i class="fas fa-download me-1"></i> Export
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Teacher</label>
                <select name="teacher_id" class="form-select">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['id'] ?>" <?= $selectedTeacher == $teacher['id'] ? 'selected' : '' ?>>
                        <?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>" <?= $selectedSubject == $subject['id'] ? 'selected' : '' ?>>
                        <?= $subject['name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= $selectedClass == $class['id'] ? 'selected' : '' ?>>
                        <?= $class['name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="homework" <?= $selectedType === 'homework' ? 'selected' : '' ?>>Homework</option>
                    <option value="quiz" <?= $selectedType === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                    <option value="project" <?= $selectedType === 'project' ? 'selected' : '' ?>>Project</option>
                    <option value="essay" <?= $selectedType === 'essay' ? 'selected' : '' ?>>Essay</option>
                    <option value="lab" <?= $selectedType === 'lab' ? 'selected' : '' ?>>Lab</option>
                    <option value="presentation" <?= $selectedType === 'presentation' ? 'selected' : '' ?>>Presentation</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="expired" <?= $selectedStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="pending_grading" <?= $selectedStatus === 'pending_grading' ? 'selected' : '' ?>>Pending Grading</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($assignments as $assignment): 
        $dueDate = strtotime($assignment['due_date']);
        $now = time();
        $daysLeft = ceil(($dueDate - $now) / (60*60*24));
        $isExpired = $daysLeft < 0;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100" style="border-left: 4px solid <?= $isExpired ? '#ef4444' : ($daysLeft <= 3 ? '#f59e0b' : '#6366f1') ?>;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0"><?= $assignment['title'] ?></h6>
                    <small class="text-muted">
                        <?= $assignment['first_name'] . ' ' . $assignment['last_name'] ?>
                        <span class="mx-1">|</span>
                        <?= $assignment['subject_name'] ?>
                    </small>
                </div>
                <span class="badge bg-<?= $isExpired ? 'danger' : ($daysLeft <= 3 ? 'warning' : 'primary') ?>">
                    <?= ucfirst($assignment['assignment_type'] ?? 'homework') ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2"><?= substr($assignment['description'], 0, 100) ?><?= strlen($assignment['description']) > 100 ? '...' : '' ?></p>
                
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <small class="text-muted">Due Date</small><br>
                        <strong class="small"><?= formatDate($assignment['due_date']) ?></strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Total Marks</small><br>
                        <strong class="small"><?= $assignment['total_marks'] ?></strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Classes</small><br>
                        <strong class="small"><?= $assignment['class_count'] ?: 1 ?></strong>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-info"><?= $assignment['submission_count'] ?> submissions</span>
                        <?php if ($assignment['pending_grading'] > 0): ?>
                        <span class="badge bg-warning"><?= $assignment['pending_grading'] ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $assignment['id'] ?>">
                        <i class="fas fa-eye"></i> View
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal<?= $assignment['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $assignment['title'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Teacher:</strong> <?= $assignment['first_name'] . ' ' . $assignment['last_name'] ?><br>
                            <strong>Subject:</strong> <?= $assignment['subject_name'] ?> (<?= $assignment['subject_code'] ?>)<br>
                            <strong>Class:</strong> <?= $assignment['class_name'] ?: 'Multiple Classes' ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Type:</strong> <?= ucfirst($assignment['assignment_type'] ?? 'homework') ?><br>
                            <strong>Due Date:</strong> <?= formatDateTime($assignment['due_date']) ?><br>
                            <strong>Total Marks:</strong> <?= $assignment['total_marks'] ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p class="mt-1"><?= nl2br($assignment['description']) ?></p>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 class="text-primary"><?= $assignment['submission_count'] ?></h4>
                            <small class="text-muted">Submissions</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-warning"><?= $assignment['pending_grading'] ?></h4>
                            <small class="text-muted">Pending Grading</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-<?= $isExpired ? 'danger' : 'success' ?>">
                                <?= $isExpired ? 'Expired' : $daysLeft . ' days' ?>
                            </h4>
                            <small class="text-muted">Status</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($assignments)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No assignments found</h5>
                <p class="text-muted">No teacher assignments match your filters</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', '1');
    window.location.href = '?' + params.toString();
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
