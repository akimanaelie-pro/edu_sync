<?php 
$page = 'grades';
$pageTitle = 'Academic Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

$academicYear = getAcademicYear();
$currentTerm = getCurrentTerm();

$action = $_GET['action'] ?? 'list';
$selectedScale = $_GET['scale_id'] ?? $db->selectOne("SELECT id FROM grade_scales WHERE is_default = 1")['id'] ?? 1;
$showNumeric = $_GET['show_numeric'] ?? 'yes';

// Handle saving grade scale settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scale_settings'])) {
    $db->update('grade_scales', ['is_default' => 0], '1=1');
    $db->update('grade_scales', ['is_default' => 1], 'id = :id', ['id' => $_POST['default_scale_id']]);
    redirect(SITE_URL . '/grades/?scale_id=' . $_POST['default_scale_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $studentId = $_POST['student_id'];
    $subjectId = $_POST['subject_id'];
    $assessmentType = $_POST['assessment_type'];
    $score = $_POST['score'];
    $maxScore = $_POST['max_score'];
    $comments = $_POST['comments'] ?? '';
    $scaleId = $_POST['grade_scale_id'] ?? $selectedScale;
    
    $percentage = ($score / $maxScore) * 100;
    $gradeLetter = calculateGradeLetter($percentage, $scaleId);
    
    $existing = $db->selectOne("
        SELECT id FROM grades 
        WHERE student_id = ? AND subject_id = ? AND assessment_type = ? 
        AND academic_year = ? AND term = ?
    ", [$studentId, $subjectId, $assessmentType, $academicYear, $currentTerm]);
    
    if ($existing) {
        $db->update('grades', [
            'score' => $score,
            'max_score' => $maxScore,
            'grade_letter' => $gradeLetter,
            'grade_scale_id' => $scaleId,
            'comments' => $comments,
            'recorded_by' => getUserId(),
            'sync_status' => 'pending'
        ], 'id = :id', ['id' => $existing['id']]);
    } else {
        $db->insert('grades', [
            'uuid' => generateUUID(),
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'academic_year' => $academicYear,
            'term' => $currentTerm,
            'assessment_type' => $assessmentType,
            'score' => $score,
            'max_score' => $maxScore,
            'grade_letter' => $gradeLetter,
            'grade_scale_id' => $scaleId,
            'comments' => $comments,
            'recorded_by' => getUserId(),
            'sync_status' => 'pending'
        ]);
    }
    
    updateStudentPerformance($db, $studentId);
    redirect(SITE_URL . '/grades/?scale_id=' . $scaleId . '&show_numeric=' . $showNumeric);
}

require_once __DIR__ . '/../config/header.php';

// Get all grade scales
$gradeScales = $db->select("SELECT * FROM grade_scales WHERE is_active = 1 ORDER BY is_default DESC, name");

// Get selected scale items
$scaleItems = $db->select("
    SELECT * FROM grade_scale_items 
    WHERE scale_id = ? 
    ORDER BY min_percentage DESC
", [$selectedScale]);

function calculateGradeLetter($percentage, $scaleId) {
    global $db;
    $item = $db->selectOne("
        SELECT grade_letter FROM grade_scale_items 
        WHERE scale_id = ? AND ? >= min_percentage AND ? <= max_percentage
        ORDER BY min_percentage DESC LIMIT 1
    ", [$scaleId, $percentage, $percentage]);
    
    return $item ? $item['grade_letter'] : 'N/A';
}

function getGradeColor($gradeLetter, $scaleId) {
    global $db;
    $item = $db->selectOne("
        SELECT color_code FROM grade_scale_items 
        WHERE scale_id = ? AND grade_letter = ?
        LIMIT 1
    ", [$scaleId, $gradeLetter]);
    
    return $item ? $item['color_code'] : '#6b7280';
}

function updateStudentPerformance($db, $studentId) {
    $avgScore = $db->selectOne("
        SELECT AVG((score / max_score) * 100) as avg 
        FROM grades 
        WHERE student_id = ? AND academic_year = ? AND term = ?
    ", [$studentId, getAcademicYear(), getCurrentTerm()]);
    
    $attendanceRate = $db->selectOne("
        SELECT (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate
        FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ", [$studentId]);
    
    $risk = 'low';
    if ($attendanceRate && $attendanceRate['rate'] < 60) $risk = 'high';
    elseif ($avgScore && $avgScore['avg'] < 50) $risk = 'high';
    elseif ($avgScore && $avgScore['avg'] < 70) $risk = 'medium';
    
    $db->update('students', ['risk_level' => $risk], 'id = :id', ['id' => $studentId]);
    
    $xpGain = 10;
    $db->query("UPDATE students SET xp_points = xp_points + $xpGain WHERE id = ?", [$studentId]);
}

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$subjects = $db->select("SELECT sub.*, c.name as class_name FROM subjects sub LEFT JOIN classes c ON sub.class_id = c.id ORDER BY sub.name");
$students = $db->select("SELECT s.*, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name");

$selectedClass = $_GET['class_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';

$where = "g.academic_year = ? AND g.term = ?";
$params = [$academicYear, $currentTerm];

if ($selectedClass) {
    $where .= " AND s.class_id = ?";
    $params[] = $selectedClass;
}

if ($selectedSubject) {
    $where .= " AND g.subject_id = ?";
    $params[] = $selectedSubject;
}

$grades = $db->select("
    SELECT g.*, u.first_name, u.last_name, s.student_id, sub.name as subject_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE $where
    ORDER BY g.created_at DESC
    LIMIT 100
", $params);

$subjectStats = $db->select("
    SELECT sub.name, 
           AVG((g.score / g.max_score) * 100) as avg_score,
           COUNT(DISTINCT g.student_id) as students
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.academic_year = ? AND g.term = ?
    GROUP BY sub.id
", [$academicYear, $currentTerm]);

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$subjects = $db->select("SELECT sub.*, c.name as class_name FROM subjects sub LEFT JOIN classes c ON sub.class_id = c.id ORDER BY sub.name");
$students = $db->select("SELECT s.*, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.first_name");

$selectedClass = $_GET['class_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';

$where = "g.academic_year = ? AND g.term = ?";
$params = [$academicYear, $currentTerm];

if ($selectedClass) {
    $where .= " AND s.class_id = ?";
    $params[] = $selectedClass;
}

if ($selectedSubject) {
    $where .= " AND g.subject_id = ?";
    $params[] = $selectedSubject;
}

$grades = $db->select("
    SELECT g.*, u.first_name, u.last_name, s.student_id, sub.name as subject_name, gs.name as scale_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON g.subject_id = sub.id
    LEFT JOIN grade_scales gs ON g.grade_scale_id = gs.id
    WHERE $where
    ORDER BY g.created_at DESC
    LIMIT 100
", $params);
?>
<style>
.grade-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-block;
}
.grade-scale-card {
    border-left: 4px solid;
    transition: all 0.3s ease;
}
.grade-scale-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>

<div class="page-header">
    <h4 class="page-title">
        <span style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <i class="fas fa-chart-line me-2" style="-webkit-text-fill-color: #6366f1;"></i>Academic Management
        </span>
    </h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="exportGrades()"
                style="border-color: #06b6d4; color: #06b6d4; border-radius: 10px;">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scaleModal"
                style="border-color: #8b5cf6; color: #8b5cf6; border-radius: 10px;">
            <i class="fas fa-cog me-1"></i> Grade Scales
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
            <i class="fas fa-plus me-1"></i> Add Grade
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #6366f1, #06b6d4) 1;">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Grade Scale</label>
                        <select name="scale_id" class="form-select" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;"
                                onchange="this.form.submit()">
                            <?php foreach ($gradeScales as $scale): ?>
                            <option value="<?= $scale['id'] ?>" <?= $selectedScale == $scale['id'] ? 'selected' : '' ?>>
                                <?= $scale['name'] ?> <?= $scale['is_default'] ? '(Default)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" style="color: #06b6d4; font-weight: 500;">Show Numeric</label>
                        <select name="show_numeric" class="form-select" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;"
                                onchange="this.form.submit()">
                            <option value="yes" <?= $showNumeric == 'yes' ? 'selected' : '' ?>>Yes - Show Score</option>
                            <option value="no" <?= $showNumeric == 'no' ? 'selected' : '' ?>>No - Letter Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="class_id" class="form-select" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $selectedClass == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="subject_id" class="form-select" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>" <?= $selectedSubject == $subject['id'] ? 'selected' : '' ?>><?= $subject['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn w-100" 
                                style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px;">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #10b981, #34d399) 1;">
            <div class="card-body">
                <h6 class="mb-3" style="color: #10b981; font-weight: 600;">
                    <i class="fas fa-palette me-1"></i> Grade Scale Legend
                </h6>
                <?php foreach ($scaleItems as $item): ?>
                <div class="d-flex align-items-center mb-2">
                    <span class="grade-badge me-2" style="background: <?= $item['color_code'] ?>20; color: <?= $item['color_code'] ?>; border: 1px solid <?= $item['color_code'] ?>40;">
                        <?= $item['grade_letter'] ?>
                    </span>
                    <small class="text-muted"><?= $item['min_percentage'] ?>-<?= $item['max_percentage'] ?>% - <?= $item['description'] ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #6366f1, #06b6d4) 1;">
    <div class="card-header d-flex justify-content-between align-items-center"
         style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
        <h5 class="mb-0" style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <i class="fas fa-list me-2" style="-webkit-text-fill-color: #6366f1;"></i>Grade Records
        </h5>
        <span class="badge" style="background: linear-gradient(135deg, #6366f1, #06b6d4); color: white; padding: 8px 16px; border-radius: 10px;">
            <?= $academicYear ?> - <?= $currentTerm ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr style="background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(6,182,212,0.08));">
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>Scale</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $grade): 
                        $gradeColor = getGradeColor($grade['grade_letter'], $grade['grade_scale_id'] ?? $selectedScale);
                    ?>
                    <tr style="border-left: 4px solid transparent; transition: all 0.2s ease;"
                        onmouseover="this.style.borderLeftColor='#6366f1'; this.style.background='rgba(99,102,241,0.03)';"
                        onmouseout="this.style.borderLeftColor='transparent'; this.style.background='transparent';">
                        <td>
                            <strong style="color: #1f2937;"><?= $grade['first_name'] . ' ' . $grade['last_name'] ?></strong>
                            <br><small class="text-muted"><?= $grade['student_id'] ?></small>
                        </td>
                        <td><span style="color: #6b7280;"><?= $grade['subject_name'] ?></span></td>
                        <td><span class="badge" style="background: rgba(107,114,128,0.1); color: #6b7280; border-radius: 8px;"><?= ucfirst($grade['assessment_type']) ?></span></td>
                        <td>
                            <?php if ($showNumeric == 'yes'): ?>
                            <span style="color: #1f2937; font-weight: 500;"><?= $grade['score'] ?>/<?= $grade['max_score'] ?></span>
                            <br><small class="text-muted"><?= round(($grade['score'] / $grade['max_score']) * 100) ?>%</small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="grade-badge" style="background: <?= $gradeColor ?>20; color: <?= $gradeColor ?>; border: 1px solid <?= $gradeColor ?>40;">
                                <?= $grade['grade_letter'] ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= $grade['scale_name'] ?? 'Traditional' ?></small></td>
                        <td><small style="color: #6b7280;"><?= formatDate($grade['created_at']) ?></small></td>
                        <td>
                            <button class="btn btn-sm" style="background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.2); border-radius: 8px;"
                                    data-bs-toggle="modal" data-bs-target="#editGradeModal<?= $grade['id'] ?>"
                                    onmouseover="this.style.background='linear-gradient(135deg, #6366f1, #4f46e5)'; this.style.color='white'; this.style.borderColor='transparent';"
                                    onmouseout="this.style.background='rgba(99,102,241,0.1)'; this.style.color='#6366f1'; this.style.borderColor='rgba(99,102,241,0.2)';">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <div class="modal fade" id="editGradeModal<?= $grade['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                                <form method="POST">
                                    <div class="modal-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 2px solid rgba(99,102,241,0.1);">
                                        <h5 class="modal-title" style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                            <i class="fas fa-edit me-2" style="-webkit-text-fill-color: #6366f1;"></i>Edit Grade
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="student_id" value="<?= $grade['student_id'] ?>">
                                        <input type="hidden" name="subject_id" value="<?= $grade['subject_id'] ?>">
                                        <input type="hidden" name="assessment_type" value="<?= $grade['assessment_type'] ?>">
                                        <input type="hidden" name="grade_scale_id" value="<?= $grade['grade_scale_id'] ?? $selectedScale ?>">
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <label class="form-label" style="color: #6366f1; font-weight: 500;">Score</label>
                                                <input type="number" name="score" class="form-control" value="<?= $grade['score'] ?>" required
                                                       style="border-color: rgba(99,102,241,0.3); border-radius: 10px;">
                                            </div>
                                            <div class="col-6 mb-3">
                                                <label class="form-label" style="color: #06b6d4; font-weight: 500;">Max Score</label>
                                                <input type="number" name="max_score" class="form-control" value="<?= $grade['max_score'] ?>" required
                                                       style="border-color: rgba(6,182,212,0.3); border-radius: 10px;">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Comments</label>
                                            <textarea name="comments" class="form-control" rows="2" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;"><?= $grade['comments'] ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer" style="background: rgba(99,102,241,0.02);">
                                        <button type="submit" name="save_grade" class="btn" 
                                                style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px; padding: 8px 24px;">
                                            <i class="fas fa-save me-1"></i> Update
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 2px solid rgba(99,102,241,0.1);">
                <h5 class="modal-title" style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-plus-circle me-2" style="-webkit-text-fill-color: #6366f1;"></i>Add New Grade
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Student</label>
                        <select name="student_id" class="form-select" required style="border-color: rgba(99,102,241,0.3); border-radius: 10px;">
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>"><?= $student['first_name'] . ' ' . $student['last_name'] ?> (<?= $student['student_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #06b6d4; font-weight: 500;">Subject</label>
                        <select name="subject_id" class="form-select" required style="border-color: rgba(6,182,212,0.3); border-radius: 10px;">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Assessment Type</label>
                        <select name="assessment_type" class="form-select" required style="border-color: rgba(139,92,246,0.3); border-radius: 10px;">
                            <option value="classwork">Classwork</option>
                            <option value="homework">Homework</option>
                            <option value="quiz">Quiz</option>
                            <option value="midterm">Midterm</option>
                            <option value="final">Final Exam</option>
                            <option value="project">Project</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #f59e0b; font-weight: 500;">Grade Scale</label>
                        <select name="grade_scale_id" class="form-select" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;">
                            <?php foreach ($gradeScales as $scale): ?>
                            <option value="<?= $scale['id'] ?>" <?= $scale['is_default'] ? 'selected' : '' ?>><?= $scale['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" style="color: #10b981; font-weight: 500;">Score</label>
                            <input type="number" name="score" class="form-control" step="0.01" required style="border-color: rgba(16,185,129,0.3); border-radius: 10px;">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" style="color: #ef4444; font-weight: 500;">Max Score</label>
                            <input type="number" name="max_score" class="form-control" value="100" required style="border-color: rgba(239,68,68,0.3); border-radius: 10px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Comments</label>
                        <textarea name="comments" class="form-control" rows="2" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: rgba(99,102,241,0.02);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 10px;">Cancel</button>
                    <button type="submit" name="save_grade" class="btn" 
                            style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px; padding: 8px 24px;">
                        <i class="fas fa-save me-1"></i> Save Grade
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="scaleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(139,92,246,0.05), rgba(99,102,241,0.05)); border-bottom: 2px solid rgba(139,92,246,0.1);">
                <h5 class="modal-title" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-cog me-2" style="-webkit-text-fill-color: #8b5cf6;"></i>Grade Scale Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-4">
                        <h6 style="color: #6366f1; font-weight: 600;">Available Grade Scales</h6>
                        <?php foreach ($gradeScales as $scale): ?>
                        <div class="card mb-2 grade-scale-card" style="border-left: 4px solid <?= $scale['is_default'] ? '#10b981' : '#6b7280' ?>;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong style="color: #1f2937;"><?= $scale['name'] ?></strong>
                                        <?php if ($scale['is_default']): ?>
                                        <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981; border-radius: 6px; font-size: 0.75rem;">Default</span>
                                        <?php endif; ?>
                                        <br><small class="text-muted"><?= $scale['description'] ?></small>
                                    </div>
                                    <?php if (!$scale['is_default']): ?>
                                    <button type="submit" name="save_scale_settings" class="btn btn-sm" 
                                            style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); border-radius: 8px;"
                                            onclick="document.querySelector('[name=default_scale_id]').value=<?= $scale['id'] ?>;">
                                        Set as Default
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <input type="hidden" name="default_scale_id" value="">
                    </div>
                    <div class="alert" style="background: rgba(99,102,241,0.05); border: 1px solid rgba(99,102,241,0.2); border-radius: 12px;">
                        <i class="fas fa-info-circle" style="color: #6366f1;"></i>
                        <small style="color: #6b7280;">Changing the default grade scale will affect how letter grades are calculated for new and updated grades. Existing grades will retain their original scale.</small>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportGrades() {
    window.location.href = 'export.php?class_id=<?= $selectedClass ?>&subject_id=<?= $selectedSubject ?>';
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>