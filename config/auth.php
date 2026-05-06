<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    public function login($email, $password) {
        $db = db();
        
        error_log("Login attempt for: $email");
        
        $user = $db->selectOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        
        if (!$user) {
            error_log("User not found: $email");
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        error_log("User found: " . print_r($user, true));
        
        if (!password_verify($password, $user['password_hash'])) {
            error_log("Password mismatch for: $email");
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_image'] = $user['profile_image'];
        
        $db->insert('sync_queue', [
            'table_name' => 'users',
            'record_id' => $user['id'],
            'operation' => 'update',
            'data' => json_encode(['last_login' => date('Y-m-d H:i:s')])
        ]);
        
        logActivity('user_login', 'User logged in: ' . $email);
        
        return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    public function register($data) {
        $db = db();
        
        if ($db->exists('users', 'email = ?', [$data['email']])) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        $uuid = generateUUID();
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $userId = $db->insert('users', [
            'uuid' => $uuid,
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role' => $data['role'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'sync_status' => 'pending'
        ]);
        
        if ($userId) {
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        $db = db();
        
        $user = $db->selectOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->update('users', ['password_hash' => $hash], 'id = :id', ['id' => $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    public function hasPermission($requiredRole) {
        $roles = ['admin' => 1, 'teacher' => 2, 'student' => 3, 'parent' => 4, 'accountant' => 5];
        $userRole = getUserRole();
        
        if (!isset($roles[$userRole]) || !isset($roles[$requiredRole])) {
            return false;
        }
        
        return $roles[$userRole] <= $roles[$requiredRole];
    }
}

$auth = new Auth();

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'login') {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        echo json_encode($auth->login($email, $password));
    }
    
    if ($_POST['action'] === 'logout') {
        echo json_encode($auth->logout());
    }
    
    if ($_POST['action'] === 'register') {
        echo json_encode($auth->register($_POST));
    }
}