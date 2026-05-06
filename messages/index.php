<?php 
$page = 'messages';
$pageTitle = 'Messages';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();

$userId = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $db->insert('messages', [
        'uuid' => generateUUID(),
        'sender_id' => $userId,
        'receiver_id' => $_POST['receiver_id'],
        'subject' => $_POST['subject'] ?? '',
        'message' => $_POST['message'],
        'message_type' => $_POST['message_type'] ?? 'direct',
        'sync_status' => 'pending'
    ]);
    redirect(SITE_URL . '/messages/');
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $db->update('messages', ['is_read' => 1], 'id = :id', ['id' => $_GET['id']]);
    redirect(SITE_URL . '/messages/');
}

require_once __DIR__ . '/../config/header.php';

$users = $db->select("SELECT id, first_name, last_name, role FROM users WHERE id != ? ORDER BY first_name", [$userId]);

$inbox = $db->select("
    SELECT m.*, u.first_name as sender_first, u.last_name as sender_last, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 50
", [$userId]);

$sent = $db->select("
    SELECT m.*, u.first_name as receiver_first, u.last_name as receiver_last, u.role as receiver_role
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = ?
    ORDER BY m.created_at DESC
    LIMIT 20
", [$userId]);

$unreadCount = $db->count('messages', "receiver_id = ? AND is_read = 0", [$userId]);
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-envelope me-2"></i>Communication Hub</h4>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-pen me-2"></i>Compose</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="receiver_id" class="form-select" required>
                            <option value="">Select Recipient</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= $user['first_name'] . ' ' . $user['last_name'] ?> (<?= ucfirst($user['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="message_type" class="form-select">
                            <option value="direct">Direct Message</option>
                            <option value="broadcast">Broadcast</option>
                        </select>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-1"></i> Send
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= SITE_URL ?>/announcements/" class="btn btn-outline-secondary">
                        <i class="fas fa-bullhorn me-2"></i> Create Announcement
                    </a>
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#smsModal">
                        <i class="fas fa-sms me-2"></i> Send SMS
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#inbox">
                            Inbox <span class="badge bg-danger"><?= $unreadCount ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sent">Sent</button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="inbox">
                        <div class="list-group list-group-flush">
                            <?php foreach ($inbox as $msg): ?>
                            <div class="list-group-item <?= $msg['is_read'] ? '' : 'bg-light' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <strong><?= $msg['sender_first'] . ' ' . $msg['sender_last'] ?></strong>
                                            <span class="badge bg-<?= $msg['sender_role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= $msg['sender_role'] ?></span>
                                            <?php if (!$msg['is_read']): ?>
                                            <span class="badge bg-danger">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-1"><?= $msg['subject'] ?: 'No subject' ?></p>
                                        <small class="text-muted"><?= $msg['message'] ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?= formatDateTime($msg['created_at']) ?></small>
                                        <?php if (!$msg['is_read']): ?>
                                        <br><a href="?action=mark_read&id=<?= $msg['id'] ?>" class="btn btn-sm btn-light mt-1">Mark Read</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($inbox)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No messages</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sent">
                        <div class="list-group list-group-flush">
                            <?php foreach ($sent as $msg): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>To: <?= $msg['receiver_first'] . ' ' . $msg['receiver_last'] ?></strong>
                                        <p class="mb-1"><?= $msg['subject'] ?: 'No subject' ?></p>
                                        <small class="text-muted"><?= $msg['message'] ?></small>
                                    </div>
                                    <small class="text-muted"><?= formatDateTime($msg['created_at']) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($sent)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-paper-plane fa-2x mb-2"></i>
                                <p>No sent messages</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="smsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sms me-2"></i>Send SMS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    SMS will be sent via Africa's Talking or configured gateway
                </div>
                <div class="mb-3">
                    <label class="form-label">Recipients</label>
                    <select class="form-select">
                        <option>All Parents</option>
                        <option>All Students</option>
                        <option>Specific Class</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" rows="3" placeholder="Type your SMS message..."></textarea>
                </div>
                <button class="btn btn-primary w-100">Send SMS</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>