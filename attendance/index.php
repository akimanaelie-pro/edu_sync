<?php 
$page = 'attendance';
$pageTitle = 'Attendance Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

// Custom attendance statuses
$customStatuses = [
    'present' => ['label' => 'Present', 'color' => 'success', 'icon' => 'check'],
    'absent' => ['label' => 'Absent', 'color' => 'danger', 'icon' => 'times'],
    'late' => ['label' => 'Late', 'color' => 'warning', 'icon' => 'clock'],
    'excused' => ['label' => 'Excused', 'color' => 'info', 'icon' => 'user-shield'],
    'medical' => ['label' => 'Medical', 'color' => 'primary', 'icon' => 'stethoscope'],
    'dress_code' => ['label' => 'Dress Code Violation', 'color' => 'secondary', 'icon' => 'tshirt'],
    'suspended' => ['label' => 'Suspended', 'color' => 'dark', 'icon' => 'ban'],
    'early_departure' => ['label' => 'Early Departure', 'color' => 'warning', 'icon' => 'sign-out-alt']
];

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedClass = $_GET['class_id'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$classes = $db->select("SELECT * FROM classes ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $studentId = $_POST['student_id'];
    $classId = $_POST['class_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    $existing = $db->selectOne("
        SELECT id FROM attendance 
        WHERE student_id = ? AND class_id = ? AND date = ?
    ", [$studentId, $classId, $date]);
    
    if ($existing) {
        $db->update('attendance', [
            'status' => $status,
            'notes' => $notes,
            'mark_by' => getUserId(),
            'mark_method' => 'manual',
            'sync_status' => 'pending'
        ], 'id = :id', ['id' => $existing['id']]);
    } else {
        $db->insert('attendance', [
            'uuid' => generateUUID(),
            'student_id' => $studentId,
            'class_id' => $classId,
            'date' => $date,
            'status' => $status,
            'mark_by' => getUserId(),
            'mark_method' => 'manual',
            'notes' => $notes,
            'sync_status' => 'pending'
        ]);
    }
    
    $student = $db->selectOne("SELECT s.*, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?", [$studentId]);
    
    if ($status === 'absent' || $status === 'suspended') {
        $attendanceRate = $db->selectOne("
            SELECT 
                (SUM(CASE WHEN status NOT IN ('absent', 'suspended') THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate
            FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", [$studentId]);
        
        if ($attendanceRate && $attendanceRate['rate'] < 75) {
            $db->update('students', ['risk_level' => 'high'], 'id = :id', ['id' => $studentId]);
            
            $guardian = $db->selectOne("SELECT g.* FROM guardians g JOIN students s ON g.id = s.guardian_id WHERE s.id = ?", [$studentId]);
            if ($guardian) {
                $db->insert('messages', [
                    'uuid' => generateUUID(),
                    'sender_id' => getUserId(),
                    'receiver_id' => $guardian['user_id'],
                    'subject' => 'Attendance Alert: ' . $student['first_name'] . ' ' . $student['last_name'],
                    'message' => 'Your child was marked ' . $status . ' on ' . $date . '. Current attendance rate: ' . round($attendanceRate['rate']) . '%',
                    'message_type' => 'notification',
                    'sync_status' => 'pending'
                ]);
            }
        }
    }
    
    redirect(SITE_URL . '/attendance/?date=' . $date . '&class_id=' . $classId . '&status=' . $selectedStatus);
}

require_once __DIR__ . '/../config/header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1, #06b6d4);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    border-color: #a5b4fc;
}
.stat-card:hover::before { opacity: 1; }
.stat-icon {
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2);
}
.page-header {
    background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(6,182,212,0.08));
    padding: 24px; border-radius: 16px; margin-bottom: 24px;
    border: 1px solid rgba(99,102,241,0.15);
}
.card {
    border: none; border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}
.card-header {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-bottom: 2px solid #e2e8f0;
    padding: 16px 20px;
}
.table thead th {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-bottom: 2px solid #cbd5e1;
    font-weight: 600; color: #475569;
    padding: 12px 16px;
}
.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));
}
.btn-primary {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border: none;
    box-shadow: 0 4px 6px -1px rgba(99,102,241,0.4);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px -2px rgba(99,102,241,0.5);
}
.form-control, .form-select {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 16px; transition: all 0.3s ease;
}
.form-control:focus, .form-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.list-group-item {
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}
.list-group-item:hover {
    border-left-color: #ef4444;
    background: rgba(239,68,68,0.05);
}
#aiMarkModal .modal-body {
    background: linear-gradient(135deg, rgba(99,102,241,0.02), rgba(6,182,212,0.02));
}
.alert-info {
    background: linear-gradient(135deg, rgba(6,182,212,0.1), rgba(99,102,241,0.1));
    border: 2px solid rgba(6,182,212,0.3);
    border-radius: 12px;
}
</style>

<?php
// Build query with filters
$where = "a.date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($selectedClass) {
    $where .= " AND a.class_id = ?";
    $params[] = $selectedClass;
}

if ($selectedStatus) {
    $where .= " AND a.status = ?";
    $params[] = $selectedStatus;
}

$attendanceRecords = $db->select("
    SELECT a.*, u.first_name, u.last_name, s.student_id, c.name as class_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE $where
    ORDER BY a.date DESC, u.first_name
", $params);

// Get stats for date range
$stats = $db->selectOne("
    SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused,
        SUM(CASE WHEN status = 'medical' THEN 1 ELSE 0 END) as medical,
        SUM(CASE WHEN status = 'dress_code' THEN 1 ELSE 0 END) as dress_code,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
        SUM(CASE WHEN status = 'early_departure' THEN 1 ELSE 0 END) as early_departure,
        COUNT(*) as total
    FROM attendance 
    WHERE date BETWEEN ? AND ? AND class_id LIKE ?
", [$startDate, $endDate, $selectedClass ? $selectedClass : '%']);

// Chronic absenteeism - students with >20% absences in date range
$chronicAbsenteeism = $db->select("
    SELECT 
        s.id, u.first_name, u.last_name, s.student_id, c.name as class_name,
        COUNT(*) as total_days,
        SUM(CASE WHEN a.status IN ('absent', 'suspended') THEN 1 ELSE 0 END) as absent_days,
        ROUND((SUM(CASE WHEN a.status IN ('absent', 'suspended') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as absent_rate
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE a.date BETWEEN ? AND ?
    " . ($selectedClass ? "AND a.class_id = ?" : "") . "
    GROUP BY s.id
    HAVING absent_rate > 20
    ORDER BY absent_rate DESC
    LIMIT 10
", $selectedClass ? [$startDate, $endDate, $selectedClass] : [$startDate, $endDate]);

$studentsNotMarked = [];
if ($selectedClass) {
    $studentsNotMarked = $db->select("
        SELECT s.id, u.first_name, u.last_name, s.student_id
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
        AND s.id NOT IN (
            SELECT student_id FROM attendance WHERE class_id = ? AND date = ?
        )
        ORDER BY u.first_name
    ", [$selectedClass, $selectedClass, $selectedDate]);
}
?>
<div class="page-header" style="position: relative; overflow: hidden;">
    <div style="position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; 
                background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%); border-radius: 50%;">
    </div>
    <div style="position: absolute; bottom: -30%; left: -5%; width: 200px; height: 200px; 
                background: radial-gradient(circle, rgba(6,182,212,0.1) 0%, transparent 70%); border-radius: 50%;">
    </div>
    <div style="position: relative; z-index: 1;">
        <h4 class="page-title">
            <span style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                <i class="fas fa-calendar-check me-2" style="-webkit-text-fill-color: #6366f1;"></i>Attendance System
            </span>
        </h4>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Track and manage student attendance with advanced filtering</p>
    </div>
    <div class="d-flex gap-2" style="position: relative; z-index: 1;">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aiMarkModal" 
                style="border-color: #6366f1; color: #6366f1; border-radius: 10px; transition: all 0.3s ease;"
                onmouseover="this.style.background='linear-gradient(135deg, #6366f1, #4f46e5)'; this.style.color='white'; this.style.borderColor='transparent';"
                onmouseout="this.style.background='transparent'; this.style.color='#6366f1'; this.style.borderColor='#6366f1';">
            <i class="fas fa-robot me-1"></i> AI Face Recognition
        </button>
        <button class="btn btn-outline-secondary" onclick="downloadAttendance()"
                style="border-color: #06b6d4; color: #06b6d4; border-radius: 10px; transition: all 0.3s ease;"
                onmouseover="this.style.background='linear-gradient(135deg, #06b6d4, #0891b2)'; this.style.color='white'; this.style.borderColor='transparent';"
                onmouseout="this.style.background='transparent'; this.style.color='#06b6d4'; this.style.borderColor='#06b6d4';">
            <i class="fas fa-download me-1"></i> Export
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php 
    $colorGradients = [
        'success' => ['bg' => 'rgba(16,185,129,0.08)', 'gradient' => '#10b981, #34d399', 'text' => '#10b981, #059669'],
        'danger' => ['bg' => 'rgba(239,68,68,0.08)', 'gradient' => '#ef4444, #f87171', 'text' => '#ef4444, #dc2626'],
        'warning' => ['bg' => 'rgba(245,158,11,0.08)', 'gradient' => '#f59e0b, #fbbf24', 'text' => '#f59e0b, #d97706'],
        'info' => ['bg' => 'rgba(6,182,212,0.08)', 'gradient' => '#06b6d4, #22d3ee', 'text' => '#06b6d4, #0891b2'],
        'primary' => ['bg' => 'rgba(99,102,241,0.08)', 'gradient' => '#6366f1, #818cf8', 'text' => '#6366f1, #4f46e5'],
        'secondary' => ['bg' => 'rgba(107,114,128,0.08)', 'gradient' => '#6b7280, #9ca3af', 'text' => '#6b7280, #4b5563'],
        'dark' => ['bg' => 'rgba(31,41,55,0.08)', 'gradient' => '#1f2937, #374151', 'text' => '#1f2937, #111827']
    ];
    
    foreach ($customStatuses as $key => $statusInfo) {
        $colors = $colorGradients[$statusInfo['color']] ?? $colorGradients['primary'];
    ?>
    <div class="col-md-3 col-lg-2">
        <div class="stat-card" style="background: linear-gradient(135deg, white 0%, <?= $colors['bg'] ?> 100%);">
            <div class="stat-icon" style="background: linear-gradient(135deg, <?= $colors['gradient'] ?>);">
                <i class="fas fa-<?= $statusInfo['icon'] ?>"></i>
            </div>
            <div>
                <h3 class="mb-0" style="background: linear-gradient(135deg, <?= $colors['text'] ?>);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?= $stats[$key] ?? 0 ?>
                </h3>
                <small class="text-muted"><?= $statusInfo['label'] ?></small>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="card mb-4" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #6366f1, #06b6d4) 1; background: linear-gradient(135deg, white 0%, rgba(99,102,241,0.02) 100%);">
    <div class="card-body">
        <div class="mb-3">
            <small style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 600;">
                <i class="fas fa-filter me-1" style="-webkit-text-fill-color: #6366f1;"></i> Filter Attendance Records
            </small>
        </div>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label" style="color: #6366f1; font-weight: 500;">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>" 
                       style="border-color: rgba(99,102,241,0.3);">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="color: #06b6d4; font-weight: 500;">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>"
                       style="border-color: rgba(6,182,212,0.3);">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Class</label>
                <select name="class_id" class="form-select" style="border-color: rgba(139,92,246,0.3);">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= $selectedClass == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="color: #f59e0b; font-weight: 500;">Status</label>
                <select name="status" class="form-select" style="border-color: rgba(245,158,11,0.3);">
                    <option value="">All Statuses</option>
                    <?php foreach ($customStatuses as $key => $statusInfo): ?>
                    <option value="<?= $key ?>" <?= $selectedStatus == $key ? 'selected' : '' ?>><?= $statusInfo['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn w-100" 
                        style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px;">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?= SITE_URL ?>/attendance/" class="btn w-100" 
                   style="border: 2px solid #06b6d4; color: #06b6d4; border-radius: 10px; background: transparent;
                          transition: all 0.3s ease;"
                   onmouseover="this.style.background='linear-gradient(135deg, #06b6d4, #0891b2)'; this.style.color='white'; this.style.borderColor='transparent';"
                   onmouseout="this.style.background='transparent'; this.style.color='#06b6d4'; this.style.borderColor='#06b6d4';">
                    <i class="fas fa-redo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #6366f1, #06b6d4) 1;">
            <div class="card-header d-flex justify-content-between align-items-center" 
                 style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
                <h5 class="mb-0" style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-list me-2" style="-webkit-text-fill-color: #6366f1;"></i>Attendance Records
                </h5>
                <span class="badge" style="background: linear-gradient(135deg, #6366f1, #06b6d4); color: white; padding: 8px 16px; border-radius: 10px;">
                    <?= $startDate ?> to <?= $endDate ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr style="background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(6,182,212,0.08));">
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceRecords)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div style="color: #9ca3af;">
                                        <i class="fas fa-inbox fa-3x mb-3" style="background: linear-gradient(135deg, #d1d5db, #9ca3af); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                        <p class="mb-0">No attendance records found with current filters</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr style="border-left: 4px solid transparent; transition: all 0.2s ease;"
                                onmouseover="this.style.borderLeftColor='#6366f1'; this.style.background='rgba(99,102,241,0.03)';"
                                onmouseout="this.style.borderLeftColor='transparent'; this.style.background='transparent';">
                                <td>
                                    <strong style="color: #1f2937;"><?= $record['first_name'] . ' ' . $record['last_name'] ?></strong>
                                </td>
                                <td><code style="background: rgba(99,102,241,0.1); color: #6366f1; padding: 4px 8px; border-radius: 6px;"><?= $record['student_id'] ?></code></td>
                                <td><span style="color: #6b7280;"><?= $record['class_name'] ?? '-' ?></span></td>
                                <td><small style="color: #6b7280;"><?= formatDate($record['date']) ?></small></td>
                                <td>
                                    <?php 
                                    $statusKey = $record['status'];
                                    $statusInfo = $customStatuses[$statusKey] ?? ['label' => ucfirst($statusKey), 'color' => 'secondary', 'icon' => 'question'];
                                    $badgeColors = [
                                        'success' => ['bg' => 'rgba(16,185,129,0.1)', 'text' => '#10b981', 'border' => 'rgba(16,185,129,0.3)'],
                                        'danger' => ['bg' => 'rgba(239,68,68,0.1)', 'text' => '#ef4444', 'border' => 'rgba(239,68,68,0.3)'],
                                        'warning' => ['bg' => 'rgba(245,158,11,0.1)', 'text' => '#f59e0b', 'border' => 'rgba(245,158,11,0.3)'],
                                        'info' => ['bg' => 'rgba(6,182,212,0.1)', 'text' => '#06b6d4', 'border' => 'rgba(6,182,212,0.3)'],
                                        'primary' => ['bg' => 'rgba(99,102,241,0.1)', 'text' => '#6366f1', 'border' => 'rgba(99,102,241,0.3)'],
                                        'secondary' => ['bg' => 'rgba(107,114,128,0.1)', 'text' => '#6b7280', 'border' => 'rgba(107,114,128,0.3)'],
                                        'dark' => ['bg' => 'rgba(31,41,55,0.1)', 'text' => '#1f2937', 'border' => 'rgba(31,41,55,0.3)']
                                    ];
                                    $badgeStyle = $badgeColors[$statusInfo['color']] ?? $badgeColors['secondary'];
                                    ?>
                                    <span class="badge" style="background: <?= $badgeStyle['bg'] ?>; color: <?= $badgeStyle['text'] ?>; 
                                                          border: 1px solid <?= $badgeStyle['border'] ?>; padding: 6px 12px; border-radius: 8px;">
                                        <i class="fas fa-<?= $statusInfo['icon'] ?> me-1"></i>
                                        <?= $statusInfo['label'] ?>
                                    </span>
                                </td>
                                <td><small style="color: #6b7280;"><?= $record['mark_method'] ?></small></td>
                                <td><small style="color: #9ca3af;"><?= $record['notes'] ?: '-' ?></small></td>
                                <td>
                                    <button class="btn btn-sm" style="background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.2); border-radius: 8px;"
                                            data-bs-toggle="modal" data-bs-target="#editModal<?= $record['id'] ?>"
                                            onmouseover="this.style.background='linear-gradient(135deg, #6366f1, #4f46e5)'; this.style.color='white'; this.style.borderColor='transparent';"
                                            onmouseout="this.style.background='rgba(99,102,241,0.1)'; this.style.color='#6366f1'; this.style.borderColor='rgba(99,102,241,0.2)';">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <div class="modal fade" id="editModal<?= $record['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                                        <div class="modal-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 2px solid rgba(99,102,241,0.1);">
                                            <h5 class="modal-title" style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                                <i class="fas fa-edit me-2" style="-webkit-text-fill-color: #6366f1;"></i>Edit Attendance
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="student_id" value="<?= $record['student_id'] ?>">
                                                <input type="hidden" name="class_id" value="<?= $record['class_id'] ?>">
                                                <input type="hidden" name="date" value="<?= $record['date'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label" style="color: #6366f1; font-weight: 500;">Status</label>
                                                    <select name="status" class="form-select" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;">
                                                        <?php foreach ($customStatuses as $key => $statusInfo): ?>
                                                        <option value="<?= $key ?>" <?= $record['status'] === $key ? 'selected' : '' ?>>
                                                            <?= $statusInfo['label'] ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" style="color: #06b6d4; font-weight: 500;">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="2" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;"><?= $record['notes'] ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer" style="background: rgba(99,102,241,0.02);">
                                                <button type="submit" name="mark_attendance" class="btn" 
                                                        style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; border-radius: 10px; padding: 8px 24px;">
                                                    <i class="fas fa-save me-1"></i> Update
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #10b981, #34d399) 1; background: linear-gradient(135deg, white 0%, rgba(16,185,129,0.03) 100%);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.05), rgba(52,211,153,0.05));">
                <h5 class="mb-0" style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-plus-circle me-2" style="-webkit-text-fill-color: #10b981;"></i>Mark Attendance
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($studentsNotMarked)): ?>
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
                    <input type="hidden" name="date" value="<?= $selectedDate ?>">
                    <div class="mb-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Student</label>
                        <select name="student_id" class="form-select" required style="border-color: rgba(99,102,241,0.3); border-radius: 10px;">
                            <option value="">Select Student</option>
                            <?php foreach ($studentsNotMarked as $student): ?>
                            <option value="<?= $student['id'] ?>"><?= $student['first_name'] . ' ' . $student['last_name'] ?> (<?= $student['student_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #06b6d4; font-weight: 500;">Status</label>
                        <select name="status" class="form-select" required style="border-color: rgba(6,182,212,0.3); border-radius: 10px;">
                            <?php foreach ($customStatuses as $key => $statusInfo): ?>
                            <option value="<?= $key ?>">
                                <?= $statusInfo['label'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..." style="border-color: rgba(139,92,246,0.3); border-radius: 10px;"></textarea>
                    </div>
                    <button type="submit" name="mark_attendance" class="btn w-100" 
                            style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; padding: 10px;
                                   box-shadow: 0 4px 6px -1px rgba(16,185,129,0.4); transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px -2px rgba(16,185,129,0.5)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(16,185,129,0.4)';">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </form>
                <?php else: ?>
                <div class="text-center py-4" style="color: #9ca3af;">
                    <i class="fas fa-check-circle fa-3x mb-3" style="background: linear-gradient(135deg, #10b981, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    <p class="mb-0" style="color: #6b7280;">All students marked for this class</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3" style="border-left: 5px solid; border-image: linear-gradient(to bottom, #ef4444, #f87171) 1; background: linear-gradient(135deg, white 0%, rgba(239,68,68,0.03) 100%);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(248,113,113,0.05));">
                <h5 class="mb-0" style="background: linear-gradient(135deg, #ef4444, #dc2626); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-exclamation-triangle me-2" style="-webkit-text-fill-color: #ef4444;"></i>Chronic Absenteeism
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($chronicAbsenteeism)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($chronicAbsenteeism as $student): ?>
                    <div class="list-group-item px-0" style="border-left: 3px solid transparent; transition: all 0.2s ease;"
                         onmouseover="this.style.borderLeftColor='#ef4444'; this.style.background='rgba(239,68,68,0.05)';"
                         onmouseout="this.style.borderLeftColor='transparent'; this.style.background='transparent';">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong style="color: #1f2937;"><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                            <span class="badge" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 6px 12px; border-radius: 8px;">
                                <?= $student['absent_rate'] ?>% absent
                            </span>
                        </div>
                        <small class="text-muted"><?= $student['student_id'] ?> | <?= $student['class_name'] ?? 'No class' ?> | <?= $student['absent_days'] ?>/<?= $student['total_days'] ?> days</small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4" style="color: #9ca3af;">
                    <i class="fas fa-smile fa-2x mb-2" style="background: linear-gradient(135deg, #10b981, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    <p class="mb-0" style="color: #6b7280;">No chronic absenteeism detected</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aiMarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-robot me-2"></i>AI Face Recognition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="alert alert-info">
                    <i class="fas fa-camera me-2"></i>
                    AI-powered attendance marking using facial recognition. Works offline with local model.
                </div>
                <div class="border rounded p-4 mb-3" style="background: #f8fafc; min-height: 200px; display: flex; align-items: center; justify-content: center;">
                    <div>
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <p>Camera feed will appear here</p>
                    </div>
                </div>
                <button class="btn btn-primary">
                    <i class="fas fa-play me-1"></i> Start Recognition
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function downloadAttendance() {
    window.location.href = 'export.php?date=<?= $selectedDate ?>&class_id=<?= $selectedClass ?>';
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>