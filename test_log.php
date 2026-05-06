<?php
$log = "Test started at " . date('Y-m-d H:i:s') . "\n";

// Test if PHP works
$log .= "PHP Version: " . phpversion() . "\n";

// Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Test database
try {
    $dsn = "mysql:host=localhost;port=3306;dbname=edusync_nexus;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '');
    $log .= "Database connected!\n";
    
    // Test insert
    $stmt = $pdo->prepare("INSERT INTO users (uuid, email, password_hash, role, first_name, last_name, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        uniqid(),
        'test' . time() . '@example.com',
        password_hash('test', PASSWORD_DEFAULT),
        'teacher',
        'Test',
        'User',
        'pending'
    ]);
    
    if ($result) {
        $log .= "Insert successful! ID: " . $pdo->lastInsertId() . "\n";
        $pdo->exec("DELETE FROM users WHERE email = 'test" . time() . "@example.com'");
        $log .= "Cleaned up\n";
    } else {
        $log .= "Insert failed: " . print_r($stmt->errorInfo(), true) . "\n";
    }
    
} catch (Exception $e) {
    $log .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/test_result.log', $log);
echo "Test completed. Check test_result.log file.";
