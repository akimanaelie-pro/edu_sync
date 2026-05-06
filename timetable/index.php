<?php
$page = 'timetable';
$pageTitle = 'Timetable';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

// AI Timetable Generator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_generate'])) {
    $classId = $_POST['ai_class_id'] ?? null;
    $timeSlots = [
        ['08:00:00', '09:00:00'],
        ['09:00:00', '10:00:00'],
        ['10:00:00', '10:45:00'],
        ['11:00:00', '12:00:00'],
        ['14:00:00', '15:00:00'],
        ['15:00:00', '16:00:00']
    ];
    
    // Break and lunch times (not used for generation)
    $breakTime = ['10:45:00', '11:00:00'];
    $lunchTime = ['12:00:00', '14:00:00'];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    if ($classId) {
        $classes = $db->select("SELECT * FROM classes WHERE id = ?", [$classId]);
    } else {
        $classes = $db->select("SELECT * FROM classes");
    }

    $generated = 0;

    // Track global teacher assignments: teacher_id => [day => [time => class_id]]
    $globalTeacherSchedule = [];
    // Track class subject assignments: class_id => [day => [time => subject_id]]
    $classSubjectSchedule = [];

    foreach ($classes as $class) {
        $subjects = $db->select("SELECT * FROM subjects WHERE class_id = ?", [$class['id']]);
        if (empty($subjects)) {
            $subjects = $db->select("SELECT * FROM subjects LIMIT 10");
        }

        $teachers = $db->select("SELECT t.*, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id");
        if (empty($teachers)) {
            $teachers = $db->select("SELECT t.*, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id LIMIT 5");
        }

        $subjectIndex = 0;
        $attempts = 0;
        $maxAttempts = count($subjects) * count($days) * 2;

        foreach ($days as $day) {
            $daySubjectCount = 0;
            $maxSubjectsPerDay = min(4, count($subjects));

            foreach ($timeSlots as $slot) {
                if ($daySubjectCount >= $maxSubjectsPerDay) break;
                if ($attempts++ > $maxAttempts) break;

                $subject = $subjects[$subjectIndex % count($subjects)];
                $teacher = $teachers[$subjectIndex % count($teachers)];

                // Check if teacher is already assigned at this time (global check)
                $teacherConflict = false;
                if (isset($globalTeacherSchedule[$teacher['id']][$day][$slot[0]])) {
                    $teacherConflict = true;
                }

                // Check if class already has a subject at this time
                $classConflict = false;
                if (isset($classSubjectSchedule[$class['id']][$day][$slot[0]])) {
                    $classConflict = true;
                }

                // Check if this subject is already assigned to this class on this day (avoid duplicates)
                $subjectAlreadyAssignedToday = false;
                if (isset($classSubjectSchedule[$class['id']][$day])) {
                    foreach ($classSubjectSchedule[$class['id']][$day] as $time => $subjId) {
                        if ($subjId == $subject['id']) {
                            $subjectAlreadyAssignedToday = true;
                            break;
                        }
                    }
                }

                if (!$teacherConflict && !$classConflict && !$subjectAlreadyAssignedToday) {
                    $existing = $db->selectOne(
                        "SELECT id FROM timetable WHERE class_id = ? AND day_of_week = ? AND start_time = ?",
                        [$class['id'], $day, $slot[0]]
                    );

                    if (!$existing) {
                        $db->insert('timetable', [
                            'uuid' => generateUUID(),
                            'class_id' => $class['id'],
                            'subject_id' => $subject['id'],
                            'teacher_id' => $teacher['id'],
                            'day_of_week' => $day,
                            'start_time' => $slot[0],
                            'end_time' => $slot[1],
                            'room' => $class['room_number'] ?? 'Room ' . $class['id'],
                            'academic_year' => getAcademicYear(),
                            'sync_status' => 'pending'
                        ]);
                        $generated++;

                        // Track assignments
                        $globalTeacherSchedule[$teacher['id']][$day][$slot[0]] = $class['id'];
                        $classSubjectSchedule[$class['id']][$day][$slot[0]] = $subject['id'];
                        $daySubjectCount++;
                    }
                }

                $subjectIndex++;
            }
        }
    }

    $_SESSION['success'] = "AI generated $generated timetable slots!";
    redirect(SITE_URL . '/timetable/');
}

// Add single slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_slot'])) {
    $existing = $db->selectOne(
        "SELECT id FROM timetable WHERE class_id = ? AND day_of_week = ? AND start_time = ?",
        [$_POST['class_id'], $_POST['day_of_week'], $_POST['start_time']]
    );

    if ($existing) {
        echo '<script>alert("Time slot conflict detected!");</script>';
    } else {
        $db->insert('timetable', [
            'uuid' => generateUUID(),
            'class_id' => $_POST['class_id'],
            'subject_id' => $_POST['subject_id'],
            'teacher_id' => $_POST['teacher_id'],
            'day_of_week' => $_POST['day_of_week'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'room' => $_POST['room'],
            'academic_year' => getAcademicYear(),
            'sync_status' => 'pending'
        ]);
    }
    redirect(SITE_URL . '/timetable/');
}

// Delete slot
if (isset($_GET['delete'])) {
    $db->delete('timetable', 'id = ?', [$_GET['delete']]);
    $_SESSION['success'] = "Timetable slot deleted!";
    redirect(SITE_URL . '/timetable/');
}

// Delete all slots for a class
if (isset($_GET['delete_class'])) {
    $deleted = $db->delete('timetable', 'class_id = ?', [$_GET['delete_class']]);
    $_SESSION['success'] = "All timetable slots deleted for class!";
    redirect(SITE_URL . '/timetable/');
}

require_once __DIR__ . '/../config/header.php';

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$subjects = $db->select("SELECT * FROM subjects ORDER BY name");
$teachers = $db->select("SELECT t.*, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id");

$timetable = $db->select("
    SELECT t.*, sub.name as subject_name, c.name as class_name, u.first_name as teacher_first, u.last_name as teacher_last
    FROM timetable t
    JOIN subjects sub ON t.subject_id = sub.id
    JOIN classes c ON t.class_id = c.id
    JOIN teachers tea ON t.teacher_id = tea.id
    JOIN users u ON tea.user_id = u.id
    ORDER BY FIELD(t.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'), t.start_time
");

// Group timetable entries by class
$timetableByClass = [];
foreach ($timetable as $entry) {
    $timetableByClass[$entry['class_id']][] = $entry;
}

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$timeSlots = [
    ['08:00:00', '09:00:00', '08:00-09:00'],
    ['09:00:00', '10:00:00', '09:00-10:00'],
    ['10:00:00', '10:45:00', '10:00-10:45'],
    ['11:00:00', '12:00:00', '11:00-12:00'],
    ['14:00:00', '15:00:00', '14:00-15:00'],
    ['15:00:00', '16:00:00', '15:00-16:00']
];
?>

<div class="page-header">
    <h4 class="page-title"><i class="fas fa-clock me-2"></i>Timetable Management</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aiModal">
            <i class="fas fa-magic me-1"></i> AI Generator
        </button>
    </div>
</div>

<?php foreach ($classes as $class):
    $classTimetable = $timetableByClass[$class['id']] ?? [];
?>
<div class="card mb-4" data-class-id="<?= $class['id'] ?>">
    <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
        <h5 class="mb-0" style="color: #4f46e5;">
            <i class="fas fa-graduation-cap me-2"></i><?= $class['name'] ?>
            <span class="badge bg-primary ms-2"><?= count($classTimetable) ?> slots</span>
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="printClassTimetable(<?= $class['id'] ?>)">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <button class="btn btn-sm btn-primary"
                    onclick="document.querySelector('#createModal [name=class_id]').value='<?= $class['id'] ?>'"
                    data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus me-1"></i> Add Slot
            </button>
            <button class="btn btn-sm btn-danger"
                    onclick="if(confirm('Delete ALL slots for this class?')) window.location.href='?delete_class=<?= $class['id'] ?>'">
                <i class="fas fa-trash me-1"></i> Delete All
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($classTimetable)): ?>
        <div class="text-center py-4 text-muted">
            <i class="fas fa-calendar-times fa-2x mb-2"></i>
            <p class="mb-0">No timetable entries for this class</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Time</th>
                        <?php foreach ($days as $day): ?>
                        <th class="text-capitalize"><?= $day ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $allSlots = [
                        ['08:00:00', '09:00:00', '08:00-09:00', 'normal'],
                        ['09:00:00', '10:00:00', '09:00-10:00', 'normal'],
                        ['10:00:00', '10:45:00', '10:00-10:45', 'normal'],
                        ['10:45:00', '11:00:00', '10:45-11:00', 'break'],
                        ['11:00:00', '12:00:00', '11:00-12:00', 'normal'],
                        ['12:00:00', '14:00:00', '12:00-14:00', 'lunch'],
                        ['14:00:00', '15:00:00', '14:00-15:00', 'normal'],
                        ['15:00:00', '16:00:00', '15:00-16:00', 'normal']
                    ];
                    
                    foreach ($allSlots as $slot):
                        $startTime = substr($slot[0], 0, 5);
                        $isBreak = $slot[3] === 'break';
                        $isLunch = $slot[3] === 'lunch';
                        
                        if ($isBreak): ?>
                    <tr class="table-warning">
                        <td class="text-center"><strong><?= $slot[2] ?></strong><br><small class="text-muted">Break</small></td>
                        <?php foreach ($days as $day): ?>
                        <td class="text-center bg-warning bg-opacity-25" style="vertical-align: middle;">
                            <strong class="text-warning">Morning Break</strong>
                            <br><small class="text-muted">15 Minutes</small>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                        <?php elseif ($isLunch): ?>
                    <tr class="table-danger">
                        <td class="text-center"><strong><?= $slot[2] ?></strong><br><small class="text-muted">Lunch</small></td>
                        <?php foreach ($days as $day): ?>
                        <td class="text-center bg-danger bg-opacity-10" style="vertical-align: middle;">
                            <strong class="text-danger">LUNCH BREAK</strong>
                            <br><small class="text-muted">2 Hours</small>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                        <?php else: ?>
                    <tr>
                        <td class="text-center"><strong><?= $slot[2] ?></strong></td>
                        <?php foreach ($days as $day): ?>
                        <td class="align-top" style="vertical-align: top; height: 90px;">
                            <?php
                            $slotEntry = null;
                            foreach ($classTimetable as $t) {
                                if ($t['day_of_week'] === $day && substr($t['start_time'],0, 5) === $startTime) {
                                    $slotEntry = $t;
                                    break;
                                }
                            }
                            if ($slotEntry):
                            ?>
                            <div class="p-2 rounded small position-relative" style="background: rgba(99,102,241,0.1); border-left: 3px solid #6366f1;">
                                <strong><?= $slotEntry['subject_name'] ?></strong>
                                <br><small><?= $slotEntry['teacher_first'] . ' ' . $slotEntry['teacher_last'] ?></small>
                                <br><small class="text-muted"><?= $slotEntry['room'] ?></small>
                                <button class="btn btn-sm btn-danger position-absolute" 
                                        style="top: 4px; right: 4px; padding: 2px 6px; font-size: 0.7rem;"
                                        onclick="if(confirm('Delete this slot?')) window.location.href='?delete=<?= $slotEntry['id'] ?>'">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <button class="btn btn-sm btn-primary w-100" style="font-size: 0.75rem;"
                                    data-class-id="<?= $class['id'] ?>"
                                    data-day="<?= $day ?>"
                                    data-start="<?= substr($slot[0], 0, 5) ?>"
                                    data-end="<?= substr($slot[1], 0, 5) ?>"
                                    onclick="openAddModal(this)">
                                <i class="fas fa-plus"></i> Add
                            </button>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Timetable Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= $class['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Day</label>
                        <select name="day_of_week" class="form-select" required>
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room</label>
                        <input type="text" name="room" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="create_slot" class="btn btn-primary">Add Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="aiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-magic me-2"></i>AI Timetable Generator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="text-muted mb-3">Automatically generate a balanced timetable by distributing subjects across the week with smart teacher assignments.</p>

                    <div class="mb-3">
                        <label class="form-label">Select Class</label>
                        <select name="ai_class_id" class="form-select">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= $class['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave as "All Classes" to generate for every class</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>AI will:</strong> Assign subjects to time slots, distribute evenly across weekdays, avoid teacher conflicts, and set appropriate rooms.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="ai_generate" class="btn btn-primary">
                        <i class="fas fa-bolt me-1"></i> Generate Timetable
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function exportFees() {
    window.location.href = 'export.php';
}

function printClassTimetable(classId) {
    const classPanel = document.querySelector(`[data-class-id="${classId}"]`);
    if (!classPanel) return;
    const classTitle = classPanel.querySelector('h5').innerText;
    const tableContent = classPanel.querySelector('.table-responsive').outerHTML;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Timetable - ${classTitle}</title>
            <meta charset="utf-8">
            <style>
                @page {
                    size: A4;
                    margin: 10mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10pt;
                    line-height: 1.3;
                    color: #333;
                }
                h3 {
                    text-align: center;
                    font-size: 16pt;
                    margin-bottom: 15px;
                    color: #4f46e5;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 6px;
                    text-align: center;
                    vertical-align: middle;
                    font-size: 9pt;
                }
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                    font-size: 9pt;
                }
                .table-warning, .bg-warning {
                    background-color: #fff3cd !important;
                }
                .table-danger, .bg-danger {
                    background-color: #f8d7da !important;
                }
                .bg-opacity-25, .bg-opacity-10 {
                    opacity: 1 !important;
                }
                strong {
                    font-size: 9pt;
                }
                small {
                    font-size: 8pt;
                }
                button, .dropdown, .btn-close {
                    display: none !important;
                }
                .card-header div:last-child {
                    display: none !important;
                }
            </style>
        </head>
        <body>
            <h3>Timetable - ${classTitle}</h3>
            ${tableContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
