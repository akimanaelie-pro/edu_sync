<?php
// Test script to check if Quick Add API works
include 'config/config.php';
include 'config/database.php';

$db = db();

// Simulate a teacher add
$_POST['email'] = 'test_teacher_' . time() . '@example.com';
$_POST['first_name'] = 'Test';
$_POST['last_name'] = 'Teacher';
$_POST['phone'] = '+250123456789';
$_POST['department'] = 'Mathematics';
$_POST['qualification'] = 'BSc';

echo "<h2>Testing Quick Add Teacher</h2>";

$email = $_POST['email'] ?? '';
$firstName = $_POST['first_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';

echo "Email: $email<br>";
echo "First Name: $firstName<br>";
echo "Last Name: $lastName<br>";

if (!$email || !$firstName || !$lastName) {
    echo "ERROR: Missing required fields<br>";
    exit;
}

if ($db->exists('users', 'email = ?', [$email])) {
    echo "ERROR: Email already exists<br>";
    exit;
}

echo "Inserting user...<br>";

$userId = $db->insert('users', [
    'uuid' => generateUUID(),
    'email' => $email,
    'password_hash' => password_hash('teacher123', PASSWORD_DEFAULT),
    'role' => 'teacher',
    'first_name' => $firstName,
    'last_name' => $lastName,
    'phone' => $_POST['phone'],
    'sync_status' => 'pending'
]);

echo "User insert result: ";
var_dump($userId);

if ($userId) {
    echo "Inserting teacher...<br>";
    $teacherId = $db->insert('teachers', [
        'uuid' => generateUUID(),
        'user_id' => $userId,
        'employee_id' => 'T9999',
        'department' => $_POST['department'],
        'qualification' => $_POST['qualification'],
        'hire_date' => date('Y-m-d'),
        'sync_status' => 'pending'
    ]);
    
    echo "Teacher insert result: ";
    var_dump($teacherId);
    
    if ($teacherId) {
        echo "<p style='color:green'><b>SUCCESS! Teacher added.</b></p>";
        
        // Cleanup
        $db->delete('teachers', 'id = :id', ['id' => $teacherId]);
        $db->delete('users', 'id = :id', ['id' => $userId]);
        echo "Test records cleaned up.<br>";
    } else {
        echo "<p style='color:red'>FAILED: Teacher record not created</p>";
    }
} else {
    echo "<p style='color:red'>FAILED: User not created</p>";
}
