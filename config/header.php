<?php 
require_once __DIR__ . '/../config/auth.php'; 

// Fetch user profile picture if logged in
$userProfilePic = '';
if (isLoggedIn()) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = db();
        $userData = $db->selectOne("SELECT profile_picture FROM users WHERE id = ?", [getUserId()]);
        if ($userData && !empty($userData['profile_picture'])) {
            $userProfilePic = SITE_URL . '/uploads/profile_pics/' . $userData['profile_picture'];
        }
    } catch (Exception $e) {
        // Database not available, use default
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduSync Nexus - School Management System">
    <meta name="theme-color" content="#4f46e5">
    <title><?= SITE_NAME ?></title>
    <link rel="manifest" href="<?= SITE_URL ?>/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f3f4f6;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 1rem;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1rem 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .sidebar-menu i {
            width: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 1.5rem 1.5rem 1.5rem;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 1rem !important;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            border-radius: 0.75rem;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }
        
        .btn-primary:hover {
            background: #4338ca;
        }
        
        .offline-badge {
            display: none;
            background: var(--warning);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
        }
        
        .offline-badge.visible {
            display: inline-flex;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: block !important;
            }
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .badge-role {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            text-transform: uppercase;
        }
        
        .table thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
        }
        
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.625rem 0.875rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
    </style>
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-graduation-cap me-2"></i>
        <?= SITE_NAME ?>
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?= SITE_URL ?>/index.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard</a></li>
        
        <?php if (isAdmin()): ?>
        <li class="mt-3"><small class="text-white-50 px-3">Management</small></li>
        <li><a href="<?= SITE_URL ?>/students/index.php" class="<?= $page === 'students' ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i> Students</a></li>
        <li><a href="<?= SITE_URL ?>/teachers/index.php" class="<?= $page === 'teachers' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
        <li><a href="<?= SITE_URL ?>/classes/index.php" class="<?= $page === 'classes' ? 'active' : '' ?>">
            <i class="fas fa-door-open"></i> Classes</a></li>
        <li><a href="<?= SITE_URL ?>/subjects/index.php" class="<?= $page === 'subjects' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> Subjects</a></li>
        <?php endif; ?>
        
        <li class="mt-3"><small class="text-white-50 px-3">Academic</small></li>
        <li><a href="<?= SITE_URL ?>/attendance/index.php" class="<?= $page === 'attendance' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Attendance</a></li>
        <li><a href="<?= SITE_URL ?>/grades/index.php" class="<?= $page === 'grades' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Grades</a></li>
        <li><a href="<?= SITE_URL ?>/assignments/index.php" class="<?= $page === 'assignments' ? 'active' : '' ?>">
            <i class="fas fa-tasks"></i> Assignments</a></li>
        
        <li><a href="<?= SITE_URL ?>/timetable/index.php" class="<?= $page === 'timetable' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Timetable</a></li>
        
        <li class="mt-3"><small class="text-white-50 px-3">Finance</small></li>
        <li><a href="<?= SITE_URL ?>/fees/index.php" class="<?= $page === 'fees' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i> Fees</a></li>
        <li><a href="<?= SITE_URL ?>/payments/index.php" class="<?= $page === 'payments' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Payments</a></li>
        
        <li class="mt-3"><small class="text-white-50 px-3">Communication</small></li>
        <li><a href="<?= SITE_URL ?>/messages/index.php" class="<?= $page === 'messages' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Messages</a></li>
        <li><a href="<?= SITE_URL ?>/announcements/index.php" class="<?= $page === 'announcements' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i> Announcements</a></li>
        
        <li class="mt-3"><small class="text-white-50 px-3">Analytics</small></li>
        <li><a href="<?= SITE_URL ?>/analytics/index.php" class="<?= $page === 'analytics' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Analytics</a></li>
        
        <?php if (isAdmin()): ?>
        <li class="mt-3"><small class="text-white-50 px-3">System</small></li>
        <li><a href="<?= SITE_URL ?>/settings/index.php" class="<?= $page === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings</a></li>
        <?php endif; ?>
    </ul>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn d-md-none mobile-toggle" onclick="toggleSidebar()" style="display:none;">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><?= $pageTitle ?? 'Dashboard' ?></h5>
            <span class="offline-badge" id="offlineBadge">
                <i class="fas fa-wifi me-1"></i> Offline
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <img src="<?= $userProfilePic ?: 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) . '&background=4f46e5&color=fff' ?>" 
                         class="rounded-circle" width="32" height="32" style="object-fit: cover;">
                    <span><?= $_SESSION['first_name'] ?></span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= SITE_URL ?>/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>