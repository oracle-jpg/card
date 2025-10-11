<?php
session_start();
require 'db.php'; // Database connection file

// Function to set message and redirect pabalik sa form page (for error only)
function redirectWithMessage($message, $type) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: change_password_page.php");
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); // Redirect if not a POST request
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic input validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    redirectWithMessage('All password fields are required.', 'error');
}

if ($new_password !== $confirm_password) {
    redirectWithMessage('New and confirmation passwords do not match.', 'error');
}

if (strlen($new_password) < 8) {
    redirectWithMessage('New password must be at least 8 characters long.', 'error');
}

try {
    // 1. Fetch current hashed password
    // Ginamit ang 'password_hash' column name (base sa previous error)
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?"); 
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Critical error, force log out
        session_destroy();
        header("Location: index.php?error=user_data_missing");
        exit;
    }

    $hashed_password = $user['password_hash']; 

    // 2. Verify current password
    if (!password_verify($current_password, $hashed_password)) {
        redirectWithMessage('Incorrect current password. Please try again.', 'error');
    }
    
    // Check if new password is the same as the old one
    if (password_verify($new_password, $hashed_password)) {
        redirectWithMessage('The new password must be different from the current password.', 'error');
    }

    // 3. Hash the new password and update in the database
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?"); 
    $update_stmt->execute([$new_hashed_password, $user_id]);
    
    // 4. SUCCESS: AUTO-LOGOUT AND REDIRECT TO LOGIN (The Security Standard)
    $_SESSION['logout_message'] = "Password successfully updated! Please log in with your new password.";
    session_destroy();
    
    // Redirect to index page
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Password update error: " . $e->getMessage());
    redirectWithMessage('A database error occurred. Please contact the administrator.', 'error');
}
?>