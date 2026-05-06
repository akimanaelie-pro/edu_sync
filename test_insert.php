<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once 'config/database.php';

$db = db();

echo "<h2>Testing Database Insert</h2>";

// Test insert
$userId = $db->insert('users', [
    'uuid' => generateUUID(),
    'email' => 'test' . time() . '@example.com',
    'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
    'role' => 'teacher',
    'first_name' => 'Test',
    'last_name' => 'Teacher',
    'sync_status' => 'pending'
]);

echo 'User insert result: ';
var_dump($userId);

if ($userId) {
    $teacherId = $db->insert('teachers', [
        'uuid' => generateUUID(),
        'user_id' => $userId,
        'employee_id' => 'T9999',
        'hire_date' => date('Y-m-d'),
        'sync_status' => 'pending'
    ]);
    echo 'Teacher insert result: ';
    var_dump($teacherId);
    
    if ($teacherId) {
        echo "<p style='color:green'>SUCCESS: Both records inserted</p>";
        
        // Cleanup
        $db->delete('teachers', 'id = :id', ['id' => $teacherId]);
        $db->delete('users', 'id = :id', ['id' => $userId]);
        echo "<p>Test records cleaned up</p>";
    } else {
        echo "<p style='color:red'>FAILED: Teacher insert returned false</p>";
    }
} else {
    echo "<p style='color:red'>FAILED: User insert returned false</p>";
}

// Check last error
echo "<h3>PHP Error Log (last 10 lines):</h3>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
} else {
    echo "No error log file configured or found";
}
