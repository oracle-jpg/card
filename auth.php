<?php
require_once 'db.php';

// UPDATED: Tinitiyak na session_start() lang ang tatawagin kung walang active session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function current_user() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function require_role($roles) {
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles)) {
        header("Location: index.php");
        exit;
    }
}
?>