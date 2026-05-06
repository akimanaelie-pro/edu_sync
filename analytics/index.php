<?php 
$page = 'analytics';
$pageTitle = 'Analytics & Insights';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';

$db = db();
$academicYear = getAcademicYear();
$currentTerm = getCurrentTerm();

$totalStudents = $db->count('students', '1=1');
$totalTeachers = $db->count('teachers', '1=1');
$totalClasses = $db->count('classes', '1=1');

$attendanceStats = $db->selectOne("
    SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        COUNT(*) as total
    FROM attendance 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

$attendanceRate = $attendanceStats['total'] > 0 
    ? round(($attendanceStats['present'] / $attendanceStats['total']) * 100) 
    : 0;

$gradeStats = $db->select("
    SELECT sub.name, 
           AVG((g.score / g.max_score) * 100) as avg_score,
           MIN((g.score / g.max_score) * 100) as min_score,
           MAX((g.score / g.max_score) * 100) as max_score,
           COUNT(*) as count
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.academic_year = ? AND g.term = ?
    GROUP BY sub.id
    ORDER BY avg_score DESC
", [$academicYear, $currentTerm]);

$overallAvg = $db->selectOne("
    SELECT AVG((score / max_score) * 100) as avg 
    FROM grades 
    WHERE academic_year = ? AND term = ?
", [$academicYear, $currentTerm]);

$atRiskCount = $db->count('students', "risk_level = 'high'");
$highPerformerCount = $db->selectOne("
    SELECT COUNT(*) as cnt FROM students s
    JOIN grades g ON s.id = g.student_id
    WHERE g.academic_year = ? AND g.term = ?
    GROUP BY s.id
    HAVING AVG((g.score / g.max_score) * 100) >= 85
", [$academicYear, $currentTerm])['cnt'] ?? 0;

$dropoutRiskStudents = $db->select("
    SELECT s.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM attendance WHERE student_id = s.id AND status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as absent_days,
           (SELECT AVG((score / max_score) * 100) FROM grades WHERE student_id = s.id AND academic_year = ? AND term = ?) as avg_score
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.risk_level = 'high'
    LIMIT 10
", [$academicYear, $currentTerm]);

$monthlyFeeCollection = $db->select("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
    FROM fee_payments 
    WHERE status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
");

$teacherPerformance = $db->select("
    SELECT u.first_name, u.last_name, 
           COUNT(DISTINCT g.id) as grades_recorded,
           (SELECT COUNT(*) FROM attendance WHERE mark_by = u.id) as attendance_marked
    FROM users u
    JOIN teachers t ON u.id = t.user_id
    LEFT JOIN grades g ON g.recorded_by = u.id AND g.academic_year = ? AND g.term = ?
    GROUP BY u.id
    LIMIT 10
", [$academicYear, $currentTerm]);

$studentPerformanceTrend = $db->select("
    SELECT g.term, AVG((g.score / g.max_score) * 100) as avg
    FROM grades g
    WHERE g.academic_year = ?
    GROUP BY g.term
    ORDER BY FIELD(g.term, 'Term 1', 'Term 2', 'Term 3')
", [$academicYear]);
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-chart-pie me-2"></i>Analytics & AI Insights</h4>
    <div class="d-flex gap-2">
        <select class="form-select" style="width: auto;">
            <option>This Term</option>
            <option>This Year</option>
        </select>
        <button class="btn btn-outline-secondary" onclick="exportAnalytics()">
            <i class="fas fa-download me-1"></i> Export Report
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4f46e5, #818cf8);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= $totalStudents ?></h3>
                <small class="text-muted">Total Students</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-percentage"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= $attendanceRate ?>%</h3>
                <small class="text-muted">Attendance Rate</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-star"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= round($overallAvg['avg'] ?? 0) ?>%</h3>
                <small class="text-muted">Overall Avg Score</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= $atRiskCount ?></h3>
                <small class="text-muted">At-Risk Students</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="performanceTrendChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI Risk Predictions</h5>
            </div>
            <div class="card-body">
                <?php if (AI_RISK_PREDICTION): ?>
                <?php if (!empty($dropoutRiskStudents)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($dropoutRiskStudents as $student): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong>
                                <br><small class="text-muted">
                                    Absent: <?= $student['absent_days'] ?> days | Avg: <?= round($student['avg_score'] ?? 0) ?>%
                                </small>
                            </div>
                            <span class="badge bg-danger">HIGH RISK</span>
                        </div>
                        <div class="mt-2">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-danger" style="width: <?= ($student['absent_days'] / 30) * 100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Prediction based on: Attendance &lt;60%, Avg Score &lt;50%</small>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p class="mb-0">No high-risk students detected</p>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>AI Risk Prediction is disabled
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Subject Performance Heatmap</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Average</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gradeStats as $subject): ?>
                            <tr>
                                <td><?= $subject['name'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $subject['avg_score'] >= 70 ? 'success' : ($subject['avg_score'] >= 50 ? 'warning' : 'danger') ?>">
                                        <?= round($subject['avg_score']) ?>%
                                    </span>
                                </td>
                                <td><?= round($subject['min_score']) ?>%</td>
                                <td><?= round($subject['max_score']) ?>%</td>
                                <td><?= $subject['count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Fee Collection Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="feeChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Performance</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Grades Recorded</th>
                                <th>Attendance Marked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacherPerformance as $teacher): ?>
                            <tr>
                                <td><?= $teacher['first_name'] . ' ' . $teacher['last_name'] ?></td>
                                <td><?= $teacher['grades_recorded'] ?: 0 ?></td>
                                <td><?= $teacher['attendance_marked'] ?: 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-school me-2"></i>School Health Dashboard</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div classcol-6>
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-primary"><?= $attendanceRate ?>%</h4>
                            <small>Attendance</small>
                        </div>
                    </div>
                    <div classcol-6>
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-success"><?= round($overallAvg['avg'] ?? 0) ?>%</h4>
                            <small>Academic Performance</small>
                        </div>
                    </div>
                    <div classcol-6>
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-warning"><?= $totalClasses ?></h4>
                            <small>Classes</small>
                        </div>
                    </div>
                    <div classcol-6>
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-danger"><?= $atRiskCount ?></h4>
                            <small>At-Risk</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendCtx = document.getElementById('performanceTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: ['Term 1', 'Term 2', 'Term 3'],
        datasets: [{
            label: 'Average Score',
            data: [<?= implode(', ', array_map(fn($t) => round($t['avg'] ?? 0), $studentPerformanceTrend)) ?>],
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: false, min: 40, max: 100 } }
    }
});

const feeCtx = document.getElementById('feeChart').getContext('2d');
new Chart(feeCtx, {
    type: 'bar',
    data: {
        labels: [<?= implode(', ', array_map(fn($m) => "'" . $m['month'] . "'", $monthlyFeeCollection)) ?>],
        datasets: [{
            label: 'Fee Collected',
            data: [<?= implode(', ', array_map(fn($m) => $m['total'], $monthlyFeeCollection)) ?>],
            backgroundColor: '#10b981'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>