<?php
// db.php
$DB_HOST = 'localhost';
$DB_NAME = 'microfinance';
$DB_USER = 'root';
$DB_PASS = 'passwordko'; // change accordingly
$DSN = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($DSN, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

/* 🔔 Notification + Audit helpers wrapped with guards */
if (!function_exists('sendNotification')) {
    function sendNotification($pdo, $user_id, $title, $message) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $title, $message]);
    }
}

if (!function_exists('notifyRole')) {
    function notifyRole($pdo, $role, $title, $message) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $uid) {
            sendNotification($pdo, $uid, $title, $message);
        }
    }
}

if (!function_exists('logAudit')) {
    function logAudit($pdo, $user_id, $action, $details = '') {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    }
}
