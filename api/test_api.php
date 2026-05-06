<?php
header('Content-Type: application/json');

// Test if this file loads
if (isset($_GET['test'])) {
    echo json_encode(['success' => true, 'message' => 'API is working']);
    exit;
}

// Quick Add Teacher - simplified
if (isset($_POST['email']) && !empty($_POST['email'])) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    
    $db = db();
    
    $email = $_POST['email'];
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    
    // Check if email exists
    if ($db->exists('users', 'email = ?', [$email])) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Insert user
    $userId = $db->insert('users', [
        'uuid' => generateUUID(),
        'email' => $email,
        'password_hash' => password_hash('teacher123', PASSWORD_DEFAULT),
        'role' => 'teacher',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'sync_status' => 'pending'
    ]);
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
        exit;
    }
    
    // Insert teacher
    $teacherId = $db->insert('teachers', [
        'uuid' => generateUUID(),
        'user_id' => $userId,
        'employee_id' => 'T9999',
        'hire_date' => date('Y-m-d'),
        'sync_status' => 'pending'
    ]);
    
    if ($teacherId) {
        echo json_encode(['success' => true, 'message' => 'Teacher added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create teacher record']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'No data received']);
