<?php
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Display errors: " . ini_get('display_errors') . "<br>";

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Error reporting enabled<br>";

// Test database connection
try {
    $dsn = "mysql:host=localhost;port=3306;dbname=edusync_nexus;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '');
    echo "Database connected successfully!<br>";
    
    // Test simple insert
    $stmt = $pdo->prepare("INSERT INTO users (uuid, email, password_hash, role, first_name, last_name, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        uniqid(),
        'test@example.com',
        password_hash('test', PASSWORD_DEFAULT),
        'teacher',
        'Test',
        'User',
        'pending'
    ]);
    
    if ($result) {
        echo "Insert successful! ID: " . $pdo->lastInsertId() . "<br>";
        // Clean up
        $pdo->exec("DELETE FROM users WHERE email = 'test@example.com'");
        echo "Cleaned up test record<br>";
    } else {
        echo "Insert failed<br>";
        print_r($stmt->errorInfo());
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
