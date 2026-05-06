<?php 
$page = 'payments';
$pageTitle = 'Payments';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';

$db = db();

$payments = $db->select("
    SELECT fp.*, u.first_name, u.last_name, s.student_id, fs.name as fee_name, usr.first_name as recorded_by_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fee_structure fs ON fp.fee_id = fs.id
    JOIN users usr ON fp.recorded_by = usr.id
    ORDER BY fp.payment_date DESC
    LIMIT 50
");

$statusFilter = $_GET['status'] ?? '';
$where = "1=1";
$params = [];

if ($statusFilter) {
    $where .= " AND fp.status = ?";
    $params[] = $statusFilter;
}

$payments = $db->select("
    SELECT fp.*, u.first_name, u.last_name, s.student_id, fs.name as fee_name, usr.first_name as recorded_by_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fee_structure fs ON fp.fee_id = fs.id
    JOIN users usr ON fp.recorded_by = usr.id
    WHERE $where
    ORDER BY fp.payment_date DESC
    LIMIT 50
", $params);
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-receipt me-2"></i>Payments</h4>
    <button class="btn btn-outline-secondary" onclick="exportPayments()">
        <i class="fas fa-download me-1"></i> Export
    </button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Student</th>
                        <th>Fee Type</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><code><?= $payment['receipt_number'] ?></code></td>
                        <td>
                            <strong><?= $payment['first_name'] . ' ' . $payment['last_name'] ?></strong>
                            <br><small class="text-muted"><?= $payment['student_id'] ?></small>
                        </td>
                        <td><?= $payment['fee_name'] ?></td>
                        <td><strong><?= formatCurrency($payment['amount']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= str_replace('_', ' ', $payment['payment_method']) ?></span></td>
                        <td><small><?= formatDateTime($payment['payment_date']) ?></small></td>
                        <td>
                            <span class="badge bg-<?= 
                                $payment['status'] === 'completed' ? 'success' : 
                                ($payment['status'] === 'pending' ? 'warning' : 'danger') 
                            ?>">
                                <?= $payment['status'] ?>
                            </span>
                        </td>
                        <td><?= $payment['recorded_by_name'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportPayments() {
    window.location.href = 'export.php?status=<?= $statusFilter ?>';
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>