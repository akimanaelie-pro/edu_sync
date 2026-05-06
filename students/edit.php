<?php 
$page = 'students';
$pageTitle = 'Edit Student';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/header.php';

$db = db();

$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    echo '<script>location.href="' . SITE_URL . '/students/";</script>';
    exit;
}

$student = $db->selectOne("
    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image, c.name as class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", [$studentId]);

if (!$student) {
    echo '<script>location.href="' . SITE_URL . '/students/";</script>';
    exit;
}

$classes = $db->select("SELECT * FROM classes ORDER BY name");
$guardians = $db->select("SELECT * FROM guardians ORDER BY first_name");
$tagsArray = json_decode($student['tags'] ?? '[]', true);
?>
<style>
.profile-edit-header {
    background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}
</style>

<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= SITE_URL ?>/students/" class="btn btn-light">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h4 class="page-title mb-0">Edit Student</h4>
    </div>
    <a href="view.php?id=<?= $studentId ?>" class="btn btn-outline-primary">
        <i class="fas fa-eye me-1"></i> View Profile
    </a>
</div>

<div class="profile-edit-header">
    <div class="row align-items-center">
        <div class="col-auto">
            <?php if ($student['profile_image']): ?>
            <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" 
                 class="rounded-circle" width="80" height="80" style="object-fit: cover;">
            <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=fff&color=4f46e5&size=128" 
                 class="rounded-circle" width="80" height="80">
            <?php endif; ?>
        </div>
        <div class="col">
            <h3 class="mb-1"><?= $student['first_name'] . ' ' . $student['last_name'] ?></h3>
            <p class="mb-0 opacity-75"><?= $student['student_id'] ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="editStudentForm" enctype="multipart/form-data">
            <div class="text-center mb-4">
                <div class="position-relative d-inline-block">
                    <?php if ($student['profile_image']): ?>
                    <img src="<?= SITE_URL ?>/<?= $student['profile_image'] ?>" 
                         id="previewImg" class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                    <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['first_name'] . ' ' . $student['last_name']) ?>&background=4f46e5&color=fff" 
                         id="previewImg" class="rounded-circle" width="120" height="120">
                    <?php endif; ?>
                    <label for="profileImg" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-3" style="cursor:pointer;">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profileImg" class="d-none" name="profile_image" accept="image/*" onchange="previewImage(this)">
                </div>
                <small class="text-muted d-block mt-3">Click camera icon to change photo</small>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= $student['first_name'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= $student['last_name'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= $student['email'] ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= $student['phone'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= $student['date_of_birth'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="male" <?= $student['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $student['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $student['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= $student['class_id'] == $class['id'] ? 'selected' : '' ?>><?= $class['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Select</option>
                        <option value="A+" <?= $student['blood_group'] === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= $student['blood_group'] === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= $student['blood_group'] === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= $student['blood_group'] === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="O+" <?= $student['blood_group'] === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= $student['blood_group'] === 'O-' ? 'selected' : '' ?>>O-</option>
                        <option value="AB+" <?= $student['blood_group'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= $student['blood_group'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nationality</label>
                    <input type="text" name="nationality" class="form-control" value="<?= $student['nationality'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Risk Level</label>
                    <select name="risk_level" class="form-select">
                        <option value="low" <?= $student['risk_level'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $student['risk_level'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $student['risk_level'] === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= $student['address'] ?? '' ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Smart Tags</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="tag_high_performer" value="high_performer" id="tag1" <?= in_array('high_performer', $tagsArray) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tag1">High Performer</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="tag_at_risk" value="at_risk" id="tag2" <?= in_array('at_risk', $tagsArray) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tag2">At Risk</label>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 d-flex gap-2">
                <button type="button" id="updateStudentBtn" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
                <a href="view.php?id=<?= $studentId ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('updateStudentBtn').addEventListener('click', function() {
    const form = document.getElementById('editStudentForm');
    const formData = new FormData(form);
    formData.append('id', <?= $studentId ?>);
    
    fetch('<?= SITE_URL ?>/api/app.php?action=update_student', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            setTimeout(() => location.href = 'view.php?id=<?= $studentId ?>', 1000);
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(e => showToast('Error: ' + e.message, 'error'));
});
</script>