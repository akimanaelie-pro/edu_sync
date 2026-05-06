<?php 
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$page = 'dashboard';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/config/header.php';

$db = db();
$userRole = getUserRole();
$userId = getUserId();

// Get profile picture
$userProfilePic = '';
try {
    $userData = $db->selectOne("SELECT profile_picture FROM users WHERE id = ?", [$userId]);
    if ($userData && !empty($userData['profile_picture'])) {
        $userProfilePic = SITE_URL . '/uploads/profile_pics/' . $userData['profile_picture'];
    }
} catch (Exception $e) {
    // Column might not exist yet
}

// Fetch notifications
try {
    $notifications = $db->select("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 ORDER BY created_at DESC LIMIT 10", [$userId]);
} catch (Exception $e) {
    $notifications = [];
}
$unreadCount = count($notifications);

// Role-based data fetching
if (isAdmin()) {
    // Admin: Full overview
    $totalStudents = $db->count('students', '1=1');
    $totalTeachers = $db->count('teachers', '1=1');
    $totalClasses = $db->count('classes', '1=1');
    $totalRevenue = $db->selectOne("SELECT COALESCE(SUM(amount), 0) as total FROM fee_payments WHERE status = 'completed'");
    $totalRevenue = ($totalRevenue ?: ['total' => 0])['total'];
    
    // Attendance data for chart (last 7 days)
    $attendanceChartData = $db->select("
        SELECT 
            DATE(date) as day,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(date)
        ORDER BY date
    ");
    
    // Fee collection data (last 6 months)
    $feeChartData = $db->select("
        SELECT 
            DATE_FORMAT(payment_date, '%b %Y') as month,
            COALESCE(SUM(amount), 0) as total
        FROM fee_payments
        WHERE status = 'completed' 
        AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY payment_date
    ");
    
    $attendanceToday = $db->selectOne("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance WHERE date = CURDATE()
    ");
    if ($attendanceToday === false) $attendanceToday = ['present' => 0, 'total' => 0];
    
    $presentRate = $attendanceToday['total'] > 0 
        ? round(($attendanceToday['present'] / $attendanceToday['total']) * 100) 
        : 0;
    
    $recentStudents = $db->select("SELECT s.*, u.first_name, u.last_name, u.email 
        FROM students s JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC LIMIT 5");
    
    $recentPayments = $db->select("
        SELECT fp.*, u.first_name, u.last_name, s.student_id
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        JOIN users u ON s.user_id = u.id
        ORDER BY fp.payment_date DESC LIMIT 5
    ");
    
    $pendingFees = $db->count('fee_payments', "status = 'pending'");
    
    if (AI_RISK_PREDICTION) {
        $atRiskStudents = $db->select("
            SELECT s.*, u.first_name, u.last_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.risk_level = 'high'
            LIMIT 5
        ");
    }
    
    // Recent activities
    $recentActivities = $db->select("
        (SELECT 'student' as type, CONCAT(u.first_name, ' ', u.last_name) as description, s.created_at as time, 'fa-user-graduate' as icon, 'primary' as color
         FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'payment' as type, CONCAT('Payment from ', u.first_name, ' ', u.last_name) as description, fp.payment_date as time, 'fa-money-bill' as icon, 'success' as color
         FROM fee_payments fp JOIN students s ON fp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE fp.status = 'completed' ORDER BY fp.payment_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'attendance' as type, CONCAT('Attendance marked for ', COUNT(*), ' students') as description, a.date as time, 'fa-calendar-check' as icon, 'info' as color
         FROM attendance a WHERE a.date = CURDATE() GROUP BY a.date LIMIT 1)
        ORDER BY time DESC LIMIT 5
    ");

} elseif (isTeacher()) {
    // Teacher: Class + Attendance
    $teacher = $db->selectOne("SELECT t.*, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?", [$userId]);
    if ($teacher === false) $teacher = [];
    $teacherId = $teacher['id'] ?? 0;
    
    $teacherClasses = $db->select("
        SELECT c.*, COUNT(s.id) as student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id
        WHERE c.teacher_id = ?
        GROUP BY c.id
    ", [$teacherId]);
    
    $attendanceToday = $db->selectOne("
        SELECT 
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE a.date = CURDATE() AND c.teacher_id = ?
    ", [$teacherId]);
    if ($attendanceToday === false) $attendanceToday = ['present' => 0, 'total' => 0];
    
    $presentRate = $attendanceToday['total'] > 0 
        ? round(($attendanceToday['present'] / $attendanceToday['total']) * 100) 
        : 0;
    
    $attendanceChartData = $db->select("
        SELECT 
            DATE(a.date) as day,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND c.teacher_id = ?
        GROUP BY DATE(a.date)
        ORDER BY a.date
    ", [$teacherId]);
    
    $recentStudents = $db->select("
        SELECT s.*, u.first_name, u.last_name
        FROM students s 
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON s.class_id = c.id
        WHERE c.teacher_id = ?
        ORDER BY s.created_at DESC LIMIT 5
    ", [$teacherId]);
    
    $pendingAssignments = $db->select("
        SELECT a.*, c.class_name, s.subject_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE c.teacher_id = ? AND a.due_date >= CURDATE()
        ORDER BY a.due_date ASC LIMIT 5
    ", [$teacherId]);

} elseif (isStudent()) {
    // Student: Results + Timetable
    $student = $db->selectOne("SELECT s.*, u.first_name, u.last_name, c.class_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.user_id = ?", [$userId]);
    if ($student === false) $student = [];
    $studentId = $student['id'] ?? 0;
    
    $grades = $db->select("
        SELECT g.*, s.subject_name, s.subject_code
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        WHERE g.student_id = ?
        ORDER BY g.created_at DESC LIMIT 10
    ", [$studentId]);
    
    $avgScoreResult = $db->selectOne("SELECT AVG(score) as average FROM grades WHERE student_id = ?", [$studentId]);
    $avgScore = ($avgScoreResult ?: ['average' => 0])['average'];
    
    $studentAttendance = $db->selectOne("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            COUNT(*) as total
        FROM attendance 
        WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ", [$studentId]);
    if ($studentAttendance === false) $studentAttendance = ['present' => 0, 'total' => 0];
    
    $attendanceRate = $studentAttendance['total'] > 0 
        ? round(($studentAttendance['present'] / $studentAttendance['total']) * 100) 
        : 0;
    
    $dayOfWeek = date('l');
    $classId = isset($student['class_id']) ? $student['class_id'] : 0;
    $todayTimetable = $db->select("
        SELECT tt.*, s.subject_name, s.subject_code, t.first_name as teacher_first, t.last_name as teacher_last
        FROM timetable tt
        JOIN subjects s ON tt.subject_id = s.id
        JOIN teachers tc ON tt.teacher_id = tc.id
        JOIN users t ON tc.user_id = t.id
        WHERE tt.class_id = ? AND tt.day_of_week = ?
        ORDER BY tt.start_time
    ", [$classId, $dayOfWeek]);
    
    $feeResult = $db->selectOne("
        SELECT COALESCE(SUM(amount), 0) as total_due
        FROM fee_payments
        WHERE student_id = ? AND status = 'pending'
    ", [$studentId]);
    $feeBalance = ($feeResult ?: ['total_due' => 0])['total_due'];
    
    $recentPayments = $db->select("
        SELECT * FROM fee_payments
        WHERE student_id = ?
        ORDER BY payment_date DESC LIMIT 5
    ", [$studentId]);
}
?>

<div class="page-header" style="position: relative; overflow: hidden; margin-bottom: 0.75rem !important;">
    <div style="position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; 
                background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%); border-radius: 50%;"></div>
    <div style="position: relative; z-index: 1;">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= $userProfilePic ?? '' ?: 'https://ui-avatars.com/api/?name=' . urlencode(($_SESSION['first_name'] ?? 'Guest') . ' ' . ($_SESSION['last_name'] ?? '')) . '&background=4f46e5&color=fff' ?>" 
                 class="rounded-circle" width="48" height="48" style="object-fit: cover; border: 2px solid rgba(99,102,241,0.3);">
            <div>
                <h4 class="page-title mb-0">
                    <span style="background: linear-gradient(135deg, #6366f1, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.25rem;">
                        <i class="fas fa-graduation-cap me-2" style="-webkit-text-fill-color: #6366f1;"></i>Dashboard
                    </span>
                </h4>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Welcome back, <strong><?= $_SESSION['first_name'] ?? 'Guest' ?>!</strong> Here's your <?= $userRole ?> overview.</p>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons Row - Below Profile Dropdown -->
<div class="d-flex justify-content-end gap-2 mb-4">
    <!-- Notifications Dropdown -->
    <div class="dropdown">
        <button class="btn btn-outline-primary position-relative" data-bs-toggle="dropdown" style="border-color: rgba(99,102,241,0.3); color: #6366f1; border-radius: 10px;">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                <?= $unreadCount ?>
            </span>
            <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="width: 320px; max-height: 400px; overflow-y: auto; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <li class="dropdown-header" style="color: #4f46e5; font-weight: 600;">Notifications</li>
            <li><hr class="dropdown-divider"></li>
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                <li>
                    <a class="dropdown-item py-2" href="#">
                        <div class="d-flex align-items-start gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(99,102,241,0.1); color: #6366f1; flex-shrink: 0;">
                                <i class="fas fa-<?= $notif['icon'] ?? 'bell' ?>" style="font-size: 0.75rem;"></i>
                            </div>
                            <div>
                                <p class="mb-0" style="font-size: 0.85rem;"><?= htmlspecialchars($notif['message']) ?></p>
                                <small class="text-muted"><?= formatDateTime($notif['created_at']) ?></small>
                            </div>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="dropdown-item-text text-center text-muted py-3">
                    <i class="fas fa-bell-slash fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">No new notifications</p>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <button class="btn btn-outline-primary" onclick="syncData()" style="border-color: rgba(99,102,241,0.3); color: #6366f1; border-radius: 10px;">
        <i class="fas fa-sync-alt me-1" id="syncIcon"></i> <span id="syncText">Sync</span>
    </button>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddModal" style="border-radius: 10px;">
        <i class="fas fa-plus me-1"></i> Quick Add
    </button>
    <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<!-- Admin Dashboard -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8); box-shadow: 0 4px 15px rgba(99,102,241,0.3);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #4f46e5; font-size: 1.75rem;"><?= number_format($totalStudents) ?></h3>
                <small class="text-muted">Total Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(16,185,129,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 4px 15px rgba(16,185,129,0.3);">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #10b981; font-size: 1.75rem;"><?= number_format($totalTeachers) ?></h3>
                <small class="text-muted">Teachers</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(245,158,11,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(245,158,11,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
                <i class="fas fa-percentage"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #f59e0b; font-size: 1.75rem;"><?= $presentRate ?>%</h3>
                <small class="text-muted">Today's Attendance</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 15px rgba(6,182,212,0.3);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #06b6d4; font-size: 1.75rem;"><?= formatCurrency($totalRevenue) ?></h3>
                <small class="text-muted">Total Revenue</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-chart-line me-2" style="color: #6366f1;"></i>Attendance Trends (Last 7 Days)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(239,68,68,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(239,68,68,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(248,113,113,0.05)); border-bottom: 1px solid rgba(239,68,68,0.1);">
                <h5 class="mb-0" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #ef4444;"></i>AI Risk Alerts
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($atRiskStudents)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($atRiskStudents as $student): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center" style="background: transparent; border-color: rgba(239,68,68,0.1);">
                        <div>
                            <strong style="color: #1f293b;"><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                            <br><small class="text-muted"><?= $student['student_id'] ?></small>
                        </div>
                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); border-radius: 8px;">High Risk</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                    <p class="mb-0">No at-risk students detected</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(6,182,212,0.05), rgba(34,211,238,0.05)); border-bottom: 1px solid rgba(6,182,212,0.1);">
                <h5 class="mb-0" style="color: #06b6d4;">
                    <i class="fas fa-chart-bar me-2" style="color: #06b6d4;"></i>Fee Collection (Last 6 Months)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="feeChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-activity me-2" style="color: #6366f1;"></i>Recent Activities
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentActivities)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="list-group-item" style="background: transparent; border-color: rgba(99,102,241,0.1);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(99,102,241,0.1); color: #<?= $activity['color'] == 'primary' ? '6366f1' : ($activity['color'] == 'success' ? '10b981' : '06b6d4') ?>; flex-shrink: 0;">
                                <i class="fas fa-<?= $activity['icon'] ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-0" style="font-size: 0.9rem; color: #1f293b;"><?= htmlspecialchars($activity['description']) ?></p>
                                <small class="text-muted"><?= formatDateTime($activity['time']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">No recent activities</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-user-plus me-2" style="color: #6366f1;"></i>Recent Students
                </h5>
                <a href="<?= SITE_URL ?>/students/" class="btn btn-sm" style="background: rgba(99,102,241,0.1); color: #6366f1; border-radius: 8px; border: none;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: rgba(99,102,241,0.05);">
                            <tr>
                                <th>Student</th>
                                <th>ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $student): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                             class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;">
                                        <div>
                                            <strong style="color: #1f293b;"><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                            <br><small class="text-muted"><?= $student['email'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><code style="background: rgba(99,102,241,0.1); color: #6366f1; padding: 4px 8px; border-radius: 6px;"><?= $student['student_id'] ?></code></td>
                                <td><small class="text-muted"><?= formatDate($student['created_at']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.08);">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, rgba(6,182,212,0.05), rgba(34,211,238,0.05)); border-bottom: 1px solid rgba(6,182,212,0.1);">
                <h5 class="mb-0" style="color: #06b6d4;">
                    <i class="fas fa-receipt me-2" style="color: #06b6d4;"></i>Recent Payments
                </h5>
                <a href="<?= SITE_URL ?>/payments/" class="btn btn-sm" style="background: rgba(6,182,212,0.1); color: #06b6d4; border-radius: 8px; border: none;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: rgba(6,182,212,0.05);">
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td>
                                    <strong style="color: #1f293b;"><?= $payment['first_name'] . ' ' . $payment['last_name'] ?></strong>
                                    <br><small class="text-muted"><?= $payment['student_id'] ?></small>
                                </td>
                                <td><strong style="color: #06b6d4;"><?= formatCurrency($payment['amount']) ?></strong></td>
                                <td>
                                    <span class="badge" style="background: <?= $payment['status'] === 'completed' ? 'rgba(16,185,129,0.1); color: #10b981;' : 'rgba(245,158,11,0.1); color: #f59e0b;' ?> border-radius: 8px; border: 1px solid <?= $payment['status'] === 'completed' ? 'rgba(16,185,129,0.3);' : 'rgba(245,158,11,0.3);' ?>;">
                                        <?= ucfirst($payment['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card" style="background: linear-gradient(135deg, #4f46e5, #06b6d4); color: white; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.2);">
            <div class="card-body d-flex align-items-center justify-content-between" style="padding: 24px;">
                <div>
                    <h4 style="color: white;"><i class="fas fa-bullhorn me-2"></i>School Health Dashboard</h4>
                    <p class="mb-0 opacity-75">Real-time overview: Attendance <?= $presentRate ?>% | Classes <?= $totalClasses ?> | Pending Payments <?= $pendingFees ?></p>
                </div>
                <a href="<?= SITE_URL ?>/analytics/" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; backdrop-filter: blur(5px);">View Analytics</a>
            </div>
        </div>
    </div>
</div>

<?php elseif (isTeacher()): ?>
<!-- Teacher Dashboard -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8); box-shadow: 0 4px 15px rgba(99,102,241,0.3);">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #4f46e5; font-size: 1.75rem;"><?= count($teacherClasses) ?></h3>
                <small class="text-muted">My Classes</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(16,185,129,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 4px 15px rgba(16,185,129,0.3);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #10b981; font-size: 1.75rem;">
                    <?php 
                    $totalStudentsInClasses = 0;
                    foreach ($teacherClasses as $class) {
                        $totalStudentsInClasses += $class['student_count'];
                    }
                    echo $totalStudentsInClasses;
                    ?>
                </h3>
                <small class="text-muted">Total Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(245,158,11,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(245,158,11,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
                <i class="fas fa-percentage"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #f59e0b; font-size: 1.75rem;"><?= $presentRate ?>%</h3>
                <small class="text-muted">Today's Attendance</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 15px rgba(6,182,212,0.3);">
                <i class="fas fa-tasks"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #06b6d4; font-size: 1.75rem;"><?= count($pendingAssignments) ?></h3>
                <small class="text-muted">Pending Tasks</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-chart-line me-2" style="color: #6366f1;"></i>Attendance Trends (Last 7 Days)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-chalkboard-teacher me-2" style="color: #6366f1;"></i>My Classes
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($teacherClasses as $class): ?>
                    <div class="list-group-item" style="background: transparent; border-color: rgba(99,102,241,0.1);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong style="color: #1f293b;"><?= $class['class_name'] ?></strong>
                                <br><small class="text-muted"><?= $class['student_count'] ?> students</small>
                            </div>
                            <a href="<?= SITE_URL ?>/attendance/index.php?class_id=<?= $class['id'] ?>" class="btn btn-sm" style="background: rgba(99,102,241,0.1); color: #6366f1; border-radius: 8px; border: none;">Take Attendance</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(245,158,11,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(245,158,11,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(251,191,36,0.05)); border-bottom: 1px solid rgba(245,158,11,0.1);">
                <h5 class="mb-0" style="color: #f59e0b;">
                    <i class="fas fa-tasks me-2" style="color: #f59e0b;"></i>Pending Assignments
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($pendingAssignments)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingAssignments as $assignment): ?>
                    <div class="list-group-item" style="background: transparent; border-color: rgba(245,158,11,0.1);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong style="color: #1f293b;"><?= $assignment['title'] ?></strong>
                                <br><small class="text-muted"><?= $assignment['class_name'] ?> - <?= $assignment['subject_name'] ?></small>
                            </div>
                            <small class="text-muted">Due: <?= formatDate($assignment['due_date']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                    <p class="mb-0">No pending assignments</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-user-plus me-2" style="color: #6366f1;"></i>Recent Students
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: rgba(99,102,241,0.05);">
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $student): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                                             class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;">
                                        <strong style="color: #1f293b;"><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                    </div>
                                </td>
                                <td><small class="text-muted"><?= $student['student_id'] ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif (isStudent()): ?>
<!-- Student Dashboard -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8); box-shadow: 0 4px 15px rgba(99,102,241,0.3);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #4f46e5; font-size: 1.75rem;"><?= round($avgScore, 1) ?>%</h3>
                <small class="text-muted">Average Score</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(245,158,11,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(245,158,11,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
                <i class="fas fa-percentage"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #f59e0b; font-size: 1.75rem;"><?= $attendanceRate ?>%</h3>
                <small class="text-muted">Attendance (30 days)</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 15px rgba(6,182,212,0.3);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #06b6d4; font-size: 1.75rem;"><?= formatCurrency($feeBalance) ?></h3>
                <small class="text-muted">Outstanding Fees</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.2); border-radius: 16px; box-shadow: 0 4px 20px rgba(16,185,129,0.1);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 4px 15px rgba(16,185,129,0.3);">
                <i class="fas fa-door-open"></i>
            </div>
            <div>
                <h3 class="mb-0" style="color: #10b981; font-size: 1.25rem;"><?= $student['class_name'] ?? 'N/A' ?></h3>
                <small class="text-muted">My Class</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-clock me-2" style="color: #6366f1;"></i>Today's Timetable
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($todayTimetable)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($todayTimetable as $item): ?>
                    <div class="list-group-item" style="background: transparent; border-color: rgba(99,102,241,0.1);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong style="color: #1f293b;"><?= $item['subject_name'] ?></strong>
                                <br><small class="text-muted"><?= $item['subject_code'] ?> | <?= $item['teacher_first'] . ' ' . $item['teacher_last'] ?></small>
                            </div>
                            <div class="text-end">
                                <small style="color: #4f46e5; font-weight: 600;"><?= date('h:i A', strtotime($item['start_time'])) ?></small>
                                <br><small class="text-muted"><?= date('h:i A', strtotime($item['end_time'])) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-calendar-times fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">No classes scheduled for today</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(6,182,212,0.05), rgba(34,211,238,0.05)); border-bottom: 1px solid rgba(6,182,212,0.1);">
                <h5 class="mb-0" style="color: #06b6d4;">
                    <i class="fas fa-chart-line me-2" style="color: #06b6d4;"></i>Recent Grades
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($grades)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: rgba(6,182,212,0.05);">
                            <tr>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <strong style="color: #1f293b;"><?= $grade['subject_name'] ?></strong>
                                    <br><small class="text-muted"><?= $grade['subject_code'] ?></small>
                                </td>
                                <td><strong style="color: #06b6d4;"><?= $grade['score'] ?>%</strong></td>
                                <td>
                                    <span class="badge" style="background: <?= $grade['score'] >= 80 ? 'rgba(16,185,129,0.1); color: #10b981;' : ($grade['score'] >= 60 ? 'rgba(245,158,11,0.1); color: #f59e0b;' : 'rgba(239,68,68,0.1); color: #ef4444;') ?> border-radius: 8px; border: 1px solid <?= $grade['score'] >= 80 ? 'rgba(16,185,129,0.3);' : ($grade['score'] >= 60 ? 'rgba(245,158,11,0.3);' : 'rgba(239,68,68,0.3);') ?>;">
                                        <?= $grade['score'] >= 80 ? 'A' : ($grade['score'] >= 70 ? 'B' : ($grade['score'] >= 60 ? 'C' : 'D')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-chart-line fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">No grades recorded yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card" style="background: linear-gradient(135deg, #4f46e5, #06b6d4); color: white; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.2);">
            <div class="card-body d-flex align-items-center justify-content-between" style="padding: 24px;">
                <div>
                    <h4 style="color: white;"><i class="fas fa-user-graduate me-2"></i>Student Portal</h4>
                    <p class="mb-0 opacity-75">Welcome <?= $student['first_name'] ?? 'Student' ?>! You're in <?= $student['class_name'] ?? 'N/A' ?> | Attendance: <?= $attendanceRate ?>% | Outstanding: <?= formatCurrency($feeBalance) ?></p>
                </div>
                <a href="<?= SITE_URL ?>/timetable/" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; backdrop-filter: blur(5px);">View Full Timetable</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (isAdmin() || isTeacher()): ?>
// Attendance Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
const attendanceData = <?= json_encode($attendanceChartData ?? []) ?>;

// Process data for chart
const labels = attendanceData.length > 0 
    ? attendanceData.map(item => {
        const date = new Date(item.day);
        return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    })
    : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const presentData = attendanceData.length > 0
    ? attendanceData.map(item => Math.round((item.present / item.total) * 100))
    : [95, 92, 88, 94, 96, 93, 97];

new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Attendance Rate (%)',
            data: presentData,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#4f46e5'
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Attendance: ' + context.parsed.y + '%';
                    }
                }
            }
        },
        scales: { 
            y: { 
                beginAtZero: false, 
                min: 50, 
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            } 
        }
    }
});
<?php endif; ?>

<?php if (isAdmin()): ?>
// Fee Collection Chart
const feeCtx = document.getElementById('feeChart').getContext('2d');
const feeData = <?= json_encode($feeChartData ?? []) ?>;

const feeLabels = feeData.length > 0 
    ? feeData.map(item => item.month)
    : ['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'May 2026', 'Jun 2026'];

const feeAmounts = feeData.length > 0
    ? feeData.map(item => parseFloat(item.total))
    : [150000, 180000, 210000, 195000, 230000, 220000];

new Chart(feeCtx, {
    type: 'bar',
    data: {
        labels: feeLabels,
        datasets: [{
            label: 'Fee Collection (RWF)',
            data: feeAmounts,
            backgroundColor: 'rgba(6, 182, 212, 0.6)',
            borderColor: '#06b6d4',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Collection: RWF ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: { 
            y: { 
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'RWF ' + value.toLocaleString();
                    }
                }
            } 
        }
    }
});
<?php endif; ?>
</script>
