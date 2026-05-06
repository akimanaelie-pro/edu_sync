<?php
$page = 'settings';
$pageTitle = 'Change Password';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$db = db();
$userId = getUserId();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        $message = 'All fields are required';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match';
        $messageType = 'danger';
    } elseif (strlen($newPassword) < 6) {
        $message = 'New password must be at least 6 characters';
        $messageType = 'danger';
    } else {
        $user = $db->selectOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
        
        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->update('users', ['password_hash' => $newHash], 'id = :id', ['id' => $userId]);
            $message = 'Password changed successfully!';
            $messageType = 'success';
        } else {
            $message = 'Current password is incorrect';
            $messageType = 'danger';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <h4 class="page-title"><i class="fas fa-key me-2"></i>Change Password</h4>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Update Your Password</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">At least 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Change Password
                        </button>
                        <a href="<?= SITE_URL ?>/settings/" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Settings
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
