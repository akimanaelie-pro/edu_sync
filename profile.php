<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$page = 'profile';
$pageTitle = 'My Profile';
require_once __DIR__ . '/config/header.php';

$db = db();
$userId = getUserId();
$userRole = getUserRole();

// Add missing columns to users table (ignore errors if columns already exist)
$pdo = $db->getConnection();
$columnsToAdd = [
    'profile_picture' => 'VARCHAR(255) NULL',
    'phone' => 'VARCHAR(20) NULL',
    'address' => 'TEXT NULL',
    'last_login' => 'DATETIME NULL'
];

foreach ($columnsToAdd as $column => $definition) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
    } catch (Exception $e) {
        // Column already exists
    }
}

// Handle profile picture upload
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'File size must be less than 5MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
            $uploadPath = __DIR__ . '/uploads/profile_pics/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Delete old profile picture if exists
                $oldPic = $db->selectOne("SELECT profile_picture FROM users WHERE id = ?", [$userId])['profile_picture'] ?? '';
                if ($oldPic && file_exists(__DIR__ . '/uploads/profile_pics/' . $oldPic)) {
                    unlink(__DIR__ . '/uploads/profile_pics/' . $oldPic);
                }
                
                // Update database
                $db->update('users', ['profile_picture' => $filename], 'id = :id', ['id' => $userId]);
                $success = 'Profile picture updated successfully!';
            } else {
                $error = 'Failed to upload image. Please try again.';
            }
        }
    } else {
        $error = 'Please select an image to upload.';
    }
}

// Handle profile info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'First name, last name, and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db->update('users', 
            ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'phone' => $phone, 'address' => $address],
            'id = :id', 
            ['id' => $userId]
        );
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $success = 'Profile updated successfully!';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $user = $db->selectOne("SELECT password FROM users WHERE id = ?", [$userId]);
    
    if (!password_verify($currentPassword, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->update('users', ['password' => $hashedPassword], 'id = :id', ['id' => $userId]);
        $success = 'Password changed successfully!';
    }
}

// Fetch user details
$user = $db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Role-specific details
$roleDetails = null;
if (isAdmin()) {
    $roleDetails = $db->selectOne("SELECT * FROM admins WHERE user_id = ?", [$userId]);
} elseif (isTeacher()) {
    $roleDetails = $db->selectOne("
        SELECT t.*, GROUP_CONCAT(DISTINCT c.class_name) as classes 
        FROM teachers t 
        LEFT JOIN classes c ON t.id = c.teacher_id 
        WHERE t.user_id = ? 
        GROUP BY t.id
    ", [$userId]);
} elseif (isStudent()) {
    $roleDetails = $db->selectOne("
        SELECT s.*, c.class_name, c.section 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.user_id = ?
    ", [$userId]);
}

// Get profile picture URL
$profilePic = $user['profile_picture'] ?? '';
$profilePicUrl = $profilePic ? SITE_URL . '/uploads/profile_pics/' . $profilePic : 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . ' ' . $user['last_name']) . '&background=4f46e5&color=fff';
?>

<div class="page-header">
    <div>
        <h4 class="page-title">
            <i class="fas fa-user-circle me-2" style="color: #6366f1;"></i>My Profile
        </h4>
        <p class="text-muted mb-0">Manage your account settings and profile information</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; border: none; background: rgba(16,185,129,0.1); color: #10b981;">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px; border: none; background: rgba(239,68,68,0.1); color: #ef4444;">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Profile Picture Card -->
    <div class="col-lg-4">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-body text-center p-4">
                <div class="position-relative d-inline-block mb-3">
                    <img src="<?= $profilePicUrl ?>" 
                         class="rounded-circle" 
                         width="150" height="150" 
                         style="object-fit: cover; border: 4px solid rgba(99,102,241,0.3); box-shadow: 0 4px 15px rgba(99,102,241,0.2);"
                         id="profilePreview"
                         alt="Profile Picture">
                    <button class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                            style="width: 40px; height: 40px; border-radius: 50%;" 
                            onclick="document.getElementById('pictureInput').click()">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <h4 style="color: #1f293b;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge" style="background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.3); border-radius: 8px; font-size: 0.85rem;">
                    <?= ucfirst($userRole) ?>
                </span>
                
                <!-- Upload Form (Hidden) -->
                <form method="POST" enctype="multipart/form-data" id="uploadForm" style="display: none;">
                    <input type="file" name="profile_picture" id="pictureInput" accept="image/*" onchange="previewImage(this);">
                    <input type="hidden" name="upload_picture" value="1">
                </form>
                
                <div class="mt-3">
                    <button class="btn btn-sm" style="background: rgba(99,102,241,0.1); color: #6366f1; border-radius: 8px; border: none;" onclick="document.getElementById('pictureInput').click()">
                        <i class="fas fa-upload me-1"></i> Change Photo
                    </button>
                    <?php if ($profilePic): ?>
                    <button class="btn btn-sm" style="background: rgba(239,68,68,0.1); color: #ef4444; border-radius: 8px; border: none;" onclick="removePicture()">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-3" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(16,185,129,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.05), rgba(52,211,153,0.05)); border-bottom: 1px solid rgba(16,185,129,0.1);">
                <h5 class="mb-0" style="color: #10b981;">
                    <i class="fas fa-info-circle me-2"></i>Account Info
                </h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item" style="background: transparent; border-color: rgba(16,185,129,0.1);">
                        <i class="fas fa-calendar me-2" style="color: #10b981;"></i>
                        <small>Joined: <?= formatDate($user['created_at']) ?></small>
                    </li>
                    <li class="list-group-item" style="background: transparent; border-color: rgba(16,185,129,0.1);">
                        <i class="fas fa-clock me-2" style="color: #10b981;"></i>
                        <small>Last Login: <?= isset($user['last_login']) ? formatDateTime($user['last_login']) : 'N/A' ?></small>
                    </li>
                    <li class="list-group-item" style="background: transparent; border-color: rgba(16,185,129,0.1);">
                        <i class="fas fa-shield-alt me-2" style="color: #10b981;"></i>
                        <small>Role: <?= ucfirst($userRole) ?></small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Profile Details -->
    <div class="col-lg-8">
        <!-- Personal Information -->
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(99,102,241,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(99,102,241,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(6,182,212,0.05)); border-bottom: 1px solid rgba(99,102,241,0.1);">
                <h5 class="mb-0" style="color: #4f46e5;">
                    <i class="fas fa-user-edit me-2" style="color: #6366f1;"></i>Edit Profile
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">First Name</label>
                            <input type="text" name="first_name" class="form-control" style="border-radius: 10px; border-color: rgba(99,102,241,0.3);" 
                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Last Name</label>
                            <input type="text" name="last_name" class="form-control" style="border-radius: 10px; border-color: rgba(99,102,241,0.3);" 
                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Email Address</label>
                            <input type="email" name="email" class="form-control" style="border-radius: 10px; border-color: rgba(99,102,241,0.3);" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" style="border-radius: 10px; border-color: rgba(99,102,241,0.3);" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Address</label>
                            <textarea name="address" class="form-control" style="border-radius: 10px; border-color: rgba(99,102,241,0.3);" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn btn-primary" style="border-radius: 10px;">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card mt-3" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(245,158,11,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(245,158,11,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(251,191,36,0.05)); border-bottom: 1px solid rgba(245,158,11,0.1);">
                <h5 class="mb-0" style="color: #f59e0b;">
                    <i class="fas fa-key me-2" style="color: #f59e0b;"></i>Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Current Password</label>
                            <input type="password" name="current_password" class="form-control" style="border-radius: 10px; border-color: rgba(245,158,11,0.3);" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">New Password</label>
                            <input type="password" name="new_password" class="form-control" style="border-radius: 10px; border-color: rgba(245,158,11,0.3);" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="color: #1f293b; font-weight: 500;">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" style="border-radius: 10px; border-color: rgba(245,158,11,0.3);" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="change_password" class="btn" style="background: rgba(245,158,11,0.1); color: #f59e0b; border-radius: 10px; border: 1px solid rgba(245,158,11,0.3);">
                                <i class="fas fa-lock me-1"></i> Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Role-Specific Information -->
<?php if ($roleDetails): ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card" style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(6,182,212,0.15); border-radius: 16px; box-shadow: 0 4px 20px rgba(6,182,212,0.08);">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(6,182,212,0.05), rgba(34,211,238,0.05)); border-bottom: 1px solid rgba(6,182,212,0.1);">
                <h5 class="mb-0" style="color: #06b6d4;">
                    <i class="fas fa-id-card me-2" style="color: #06b6d4;"></i><?= ucfirst($userRole) ?> Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (isTeacher() && $roleDetails): ?>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Employee ID:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['employee_id'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Qualification:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['qualification'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Joining Date:</strong>
                        <p class="text-muted"><?= isset($roleDetails['joining_date']) ? formatDate($roleDetails['joining_date']) : 'N/A' ?></p>
                    </div>
                    <div class="col-md-12">
                        <strong style="color: #1f293b;">Assigned Classes:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['classes'] ?? 'No classes assigned') ?></p>
                    </div>
                    <?php elseif (isStudent() && $roleDetails): ?>
                    <div class="col-md-3">
                        <strong style="color: #1f293b;">Student ID:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['student_id'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong style="color: #1f293b;">Class:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['class_name'] ?? 'N/A') ?> <?= htmlspecialchars($roleDetails['section'] ?? '') ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong style="color: #1f293b;">Roll Number:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['roll_number'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-3">
                        <strong style="color: #1f293b;">Enrollment Date:</strong>
                        <p class="text-muted"><?= isset($roleDetails['enrollment_date']) ? formatDate($roleDetails['enrollment_date']) : 'N/A' ?></p>
                    </div>
                    <?php elseif (isAdmin() && $roleDetails): ?>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Admin ID:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['admin_id'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Department:</strong>
                        <p class="text-muted"><?= htmlspecialchars($roleDetails['department'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong style="color: #1f293b;">Role:</strong>
                        <p class="text-muted">System Administrator</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>

<script>
// Preview image before upload
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
        
        // Auto-submit form after selecting file
        setTimeout(() => {
            document.getElementById('uploadForm').submit();
        }, 500);
    }
}

// Remove profile picture
function removePicture() {
    if (confirm('Are you sure you want to remove your profile picture?')) {
        window.location.href = '<?= SITE_URL ?>/profile.php?remove_picture=1';
    }
}

<?php
// Handle picture removal
if (isset($_GET['remove_picture']) && $_GET['remove_picture'] == '1') {
    $oldPic = $db->selectOne("SELECT profile_picture FROM users WHERE id = ?", [$userId])['profile_picture'] ?? '';
    if ($oldPic && file_exists(__DIR__ . '/uploads/profile_pics/' . $oldPic)) {
        unlink(__DIR__ . '/uploads/profile_pics/' . $oldPic);
    }
    $db->update('users', ['profile_picture' => null], 'id = :id', ['id' => $userId]);
    echo "window.location.href = '" . SITE_URL . "/profile.php?success=1';";
}
?>
</script>
