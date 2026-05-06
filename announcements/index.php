<?php 
$page = 'announcements';
$pageTitle = 'Announcements';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = db();
$userId = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $db->insert('announcements', [
        'uuid' => generateUUID(),
        'title' => $_POST['title'],
        'content' => $_POST['content'],
        'posted_by' => $userId,
        'target_roles' => json_encode($_POST['target_roles'] ?? []),
        'target_classes' => json_encode($_POST['target_classes'] ?? []),
        'priority' => $_POST['priority'] ?? 'medium',
        'publish_from' => $_POST['publish_from'] ?? date('Y-m-d H:i:s'),
        'publish_until' => $_POST['publish_until'] ?? null,
        'is_published' => isset($_POST['publish_now']) ? 1 : 0,
        'sync_status' => 'pending'
    ]);
    redirect(SITE_URL . '/announcements/');
}

require_once __DIR__ . '/../config/header.php';

$announcements = $db->select("
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON a.posted_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 20
");

$classes = $db->select("SELECT * FROM classes ORDER BY name");
?>
<div class="page-header">
    <h4 class="page-title"><i class="fas fa-bullhorn me-2"></i>Announcements</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> New Announcement
    </button>
</div>

<div class="row g-3">
    <?php foreach ($announcements as $ann): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0"><?= $ann['title'] ?></h5>
                    <span class="badge bg-<?= 
                        $ann['priority'] === 'high' ? 'danger' : 
                        ($ann['priority'] === 'medium' ? 'warning' : 'info') 
                    ?>"><?= $ann['priority'] ?></span>
                    <?php if ($ann['is_published']): ?>
                    <span class="badge bg-success">Published</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Draft</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">By <?= $ann['first_name'] . ' ' . $ann['last_name'] ?> on <?= formatDate($ann['created_at']) ?></small>
            </div>
            <div class="card-body">
                <p><?= nl2br($ann['content']) ?></p>
                <?php if ($ann['target_roles'] || $ann['target_classes']): ?>
                <small class="text-muted">
                    <i class="fas fa-users me-1"></i>
                    Target: <?= implode(', ', json_decode($ann['target_roles'] ?? '[]')) ?: 'All' ?>
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($announcements)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-bullhorn fa-3x mb-3"></i>
                <h5>No announcements yet</h5>
                <p>Create your first announcement to communicate with students, parents, and staff.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Roles</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="target_roles[]" value="student" id="role1">
                            <label class="form-check-label" for="role1">Students</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="target_roles[]" value="parent" id="role2">
                            <label class="form-check-label" for="role2">Parents</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="target_roles[]" value="teacher" id="role3">
                            <label class="form-check-label" for="role3">Teachers</label>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="publish_now" value="1" id="publish" checked>
                        <label class="form-check-label" for="publish">Publish Immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="create_announcement" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>