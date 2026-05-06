<?php
$page = 'fees';
$pageTitle = 'Fee & Finance';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

$academicYear = getAcademicYear();
$currentTerm = getCurrentTerm();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_fee'])) {
    $db->insert('fee_structure', [
        'uuid' => generateUUID(),
        'name' => $_POST['name'],
        'amount' => $_POST['amount'],
        'class_id' => $_POST['class_id'] ?? null,
        'academic_year' => $academicYear,
        'term' => $_POST['term'] ?? $currentTerm,
        'due_date' => $_POST['due_date'],
        'description' => $_POST['description'] ?? '',
        'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
        'sync_status' => 'pending'
    ]);
    redirect(SITE_URL . '/fees/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $receiptNum = 'RCP' . date('Y') . str_pad($db->count('fee_payments', '1=1') + 1, 5, '0', STR_PAD_LEFT);

    $db->insert('fee_payments', [
        'uuid' => generateUUID(),
        'student_id' => $_POST['student_id'],
        'fee_id' => $_POST['fee_id'],
        'amount' => $_POST['amount'],
        'payment_method' => $_POST['payment_method'],
        'payment_date' => $_POST['payment_date'],
        'recorded_by' => getUserId(),
        'receipt_number' => $receiptNum,
        'notes' => $_POST['notes'] ?? '',
        'status' => 'completed',
        'sync_status' => 'pending'
    ]);

    $student = $db->selectOne("SELECT s.*, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?", [$_POST['student_id']]);
    $fee = $db->selectOne("SELECT * FROM fee_structure WHERE id = ?", [$_POST['fee_id']]);

    $db->insert('messages', [
        'uuid' => generateUUID(),
        'sender_id' => getUserId(),
        'receiver_id' => $student['user_id'],
        'subject' => 'Payment Received',
        'message' => "Dear {$student['first_name']}, your payment of " . formatCurrency($_POST['amount']) . " for {$fee['name']} has been received. Receipt: $receiptNum",
        'message_type' => 'notification',
        'sync_status' => 'pending'
    ]);

    redirect(SITE_URL . '/fees/');
}

require_once __DIR__ . '/../config/header.php';

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$feeStructures = $db->select("
    SELECT fs.*, c.name as class_name
    FROM fee_structure fs
    LEFT JOIN classes c ON fs.class_id = c.id
    ORDER BY fs.due_date DESC
");

$students = $db->select("
    SELECT s.*, u.first_name, u.last_name, u.email
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY u.first_name
");

$pendingPayments = $db->select("
    SELECT fp.*, u.first_name, u.last_name, fs.name as fee_name, fs.amount as total_fee
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fee_structure fs ON fp.fee_id = fs.id
    WHERE fp.status = 'pending'
    ORDER BY fp.created_at DESC
");

$completedPayments = $db->select("
    SELECT fp.*, u.first_name, u.last_name, fs.name as fee_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fee_structure fs ON fp.fee_id = fs.id
    WHERE fp.status = 'completed'
    ORDER BY fp.payment_date DESC
    LIMIT 20
");

$totalCollected = $db->selectOne("SELECT COALESCE(SUM(amount), 0) as total FROM fee_payments WHERE status = 'completed'")['total'] ?? 0;
$totalPending = $db->selectOne("SELECT COALESCE(SUM(fs.amount), 0) as total FROM fee_structure fs LEFT JOIN fee_payments fp ON fs.id = fp.fee_id AND fp.status = 'completed' WHERE fs.academic_year = ?", [$academicYear])['total'] ?? 0;

$outstandingByStudent = $db->select("
    SELECT s.id, u.first_name, u.last_name,
           COALESCE(SUM(fs.amount), 0) - COALESCE(SUM(fp.amount), 0) as outstanding
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN fee_structure fs ON (fs.class_id = s.class_id OR fs.class_id IS NULL) AND fs.academic_year = ?
    LEFT JOIN fee_payments fp ON fp.student_id = s.id AND fp.fee_id = fs.id AND fp.status = 'completed'
    GROUP BY s.id
    HAVING outstanding > 0
    ORDER BY outstanding DESC
    LIMIT 10
", [$academicYear]);
?>

<div class="page-header">
    <h4 class="page-title"><i class="fas fa-money-bill-wave me-2"></i>Fee & Finance</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="exportFees()">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeeModal">
            <i class="fas fa-plus me-1"></i> Create Fee
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= formatCurrency($totalCollected) ?></h3>
                <small class="text-muted">Total Collected</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= formatCurrency($totalPending) ?></h3>
                <small class="text-muted">Total Expected</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left: 4px solid #ef4444;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 class="mb-0"><?= count($outstandingByStudent) ?></h3>
                <small class="text-muted">Outstanding Accounts</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card" style="border-left: 4px solid #6366f1;">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05));">
                <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #6366f1;"></i>Fee Structures</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fee Name</th>
                                <th>Class</th>
                                <th>Amount</th>
                                <th>Term</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeStructures as $fee): ?>
                            <tr>
                                <td><strong style="color: #4f46e5;"><?= $fee['name'] ?></strong></td>
                                <td><span class="badge bg-info"><?= $fee['class_name'] ?? 'All Classes' ?></span></td>
                                <td><strong><?= formatCurrency($fee['amount']) ?></strong></td>
                                <td><span class="badge bg-secondary"><?= $fee['term'] ?></span></td>
                                <td><?= formatDate($fee['due_date']) ?></td>
                                <td>
                                    <?php if (strtotime($fee['due_date']) < time()): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($feeStructures)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No fee structures found</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card" style="border-left: 4px solid #06b6d4;">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(6,182,212,0.05), rgba(34,211,238,0.05));">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2" style="color: #06b6d4;"></i>Record Payment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label" style="color: #06b6d4; font-weight: 500;">Student</label>
                        <select name="student_id" class="form-select" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>"><?= $student['first_name'] . ' ' . $student['last_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Fee Type</label>
                        <select name="fee_id" class="form-select" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;" required>
                            <option value="">Select Fee</option>
                            <?php foreach ($feeStructures as $fee): ?>
                            <option value="<?= $fee['id'] ?>"><?= $fee['name'] ?> - <?= formatCurrency($fee['amount']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Amount</label>
                        <input type="number" name="amount" class="form-control" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #f59e0b; font-weight: 500;">Payment Method</label>
                        <select name="payment_method" class="form-select" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;" required>
                            <option value="cash">Cash</option>
                            <option value="mtn_momo">MTN MoMo</option>
                            <option value="airtel_money">Airtel Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: #10b981; font-weight: 500;">Payment Date</label>
                        <input type="datetime-local" name="payment_date" class="form-control" style="border-color: rgba(16,185,129,0.3); border-radius: 10px;" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" style="border-radius: 10px;"></textarea>
                    </div>
                    <button type="submit" name="record_payment" class="btn btn-primary w-100" style="border-radius: 10px;">
                        <i class="fas fa-save me-1"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card" style="border-left: 4px solid #ef4444;">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(239,68,68,0.05), rgba(248,113,113,0.05));">
                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2" style="color: #ef4444;"></i>Outstanding Balances</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outstandingByStudent as $student): ?>
                            <tr>
                                <td><strong><?= $student['first_name'] . ' ' . $student['last_name'] ?></strong></td>
                                <td class="text-danger"><strong><?= formatCurrency($student['outstanding']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($outstandingByStudent)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-2x mb-2" style="color: #10b981;"></i>
                                    <p class="mb-0">No outstanding balances</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card" style="border-left: 4px solid #10b981;">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.05), rgba(52,211,153,0.05));">
                <h5 class="mb-0"><i class="fas fa-check-double me-2" style="color: #10b981;"></i>Recent Payments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedPayments as $payment): ?>
                            <tr>
                                <td><strong><?= $payment['first_name'] . ' ' . $payment['last_name'] ?></strong></td>
                                <td><span class="text-success"><?= formatCurrency($payment['amount']) ?></span></td>
                                <td><code style="background: rgba(99,102,241,0.1); color: #6366f1; padding: 4px 8px; border-radius: 6px;"><?= $payment['receipt_number'] ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($completedPayments)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="fas fa-receipt fa-2x mb-2" style="color: #6b7280;"></i>
                                    <p class="mb-0">No payments recorded yet</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 2px solid rgba(99,102,241,0.1);">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2" style="color: #6366f1;"></i>Create Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color: #6366f1; font-weight: 500;">Fee Name</label>
                        <input type="text" name="name" class="form-control" style="border-color: rgba(99,102,241,0.3); border-radius: 10px;" placeholder="e.g., Tuition Fee" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #06b6d4; font-weight: 500;">Amount</label>
                            <input type="number" name="amount" class="form-control" style="border-color: rgba(6,182,212,0.3); border-radius: 10px;" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #8b5cf6; font-weight: 500;">Class (Optional)</label>
                            <select name="class_id" class="form-select" style="border-color: rgba(139,92,246,0.3); border-radius: 10px;">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>"><?= $class['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #f59e0b; font-weight: 500;">Term</label>
                            <select name="term" class="form-select" style="border-color: rgba(245,158,11,0.3); border-radius: 10px;">
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: #10b981; font-weight: 500;">Due Date</label>
                            <input type="date" name="due_date" class="form-control" style="border-color: rgba(16,185,129,0.3); border-radius: 10px;" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" style="border-radius: 10px;"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="recurring" checked>
                        <label class="form-check-label" for="recurring">Recurring Fee</label>
                    </div>
                </div>
                <div class="modal-footer" style="background: rgba(99,102,241,0.02);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_fee" class="btn btn-primary" style="border-radius: 10px;">
                        <i class="fas fa-save me-1"></i> Create Fee
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
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
