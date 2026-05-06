<?php
session_start();

define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'edusync_nexus');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

define('SITE_NAME', 'EduSync Nexus');
define('SITE_URL', 'http://localhost/sms');
define('CURRENCY', 'RWF');

define('OFFLINE_MODE', false);
define('SYNC_ENABLED', true);

define('AI_RISK_PREDICTION', true);
define('GAMIFICATION_ENABLED', true);

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function isParent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'parent';
}

function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getUserRole() {
    return $_SESSION['role'] ?? 'guest';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 0);
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d M Y H:i', strtotime($datetime));
}

function getAcademicYear() {
    return '2025-2026';
}

function getCurrentTerm() {
    return 'Term 1';
}

function logActivity($action, $details = '') {
    static $db = null;
    if ($db === null) {
        require_once __DIR__ . '/database.php';
        $db = db();
    }
    
    try {
        $db->insert('audit_logs', [
            'user_id' => getUserId() ?: null,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silently fail - don't break the app
    }
}