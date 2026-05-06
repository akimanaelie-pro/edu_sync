<?php
$page = 'settings';
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$userId = getUserId();

// Get filter parameters
$actionFilter = $_GET['action'] ?? '';
$limit = 50;

// Build query
$where = "1=1";
$params = [];

if ($actionFilter) {
    $where .= " AND al.action LIKE ?";
    $params[] = "%$actionFilter%";
}

// Get audit logs with user info
$logs = $db->select("
    SELECT al.*, u.first_name, u.last_name, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where
    ORDER BY al.created_at DESC
    LIMIT $limit
", $params);

// Get distinct actions for filter
$actions = $db->select("SELECT DISTINCT action FROM audit_logs ORDER BY action");

require_once __DIR__ . '/../config/header.php';
?>

<div class="page-header">
    <h4 class="page-title"><i class="fas fa-history me-2"></i>Audit Logs</h4>
    <a href="<?= SITE_URL ?>/settings/" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>System Activity Logs</h5>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act['action']) ?>" <?= $actionFilter === $act['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $act['action']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                <p>No audit logs found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small class="text-muted">
                                    <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($log['email'] ?? 'System') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $log['role'] === 'admin' ? 'primary' : ($log['role'] === 'teacher' ? 'success' : 'info') ?>">
                                    <?= ucfirst($log['role'] ?? 'system') ?>
                                </span>
                            </td>
                            <td>
                                <code class="small"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?></code>
                            </td>
                            <td>
                                <small><?= htmlspecialchars(substr($log['details'] ?? '', 0, 100)) ?><?= strlen($log['details'] ?? '') > 100 ? '...' : '' ?></small>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '') ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportLogs() {
    window.location.href = '<?= SITE_URL ?>/api/export_logs.php';
}
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
