<?php
// REMOVED: if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'auth.php';
require_once 'db.php';

// Make sure only clients can upload
require_role(['client']);

$user_id = $_SESSION['user_id'] ?? null; // Use null coalescing for safety

// SECURITY CHECK: If $user_id is not set, the user is not logged in.
if (!$user_id) {
    // You should redirect to the login page or output a proper error.
    die("🚫 Error: Not authenticated. Please log in.");
}

// Check if the client is linked to a member record
$stmt = $pdo->prepare("SELECT id, user_id FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();

// CRITICAL FIX: The previous line 'if (!$member && $user['role'] === 'client')' was causing:
// 1. Warning: Undefined variable $user
// 2. Warning: Trying to access array offset on value of type null/bool
// The check on $user['role'] is likely redundant if require_role(['client']) already ran.
// However, to fix the specific error, we check if $user is set before accessing its role.
// I am assuming a variable $user is loaded by auth.php, but this is the safest way to check.
// If $user is not defined in auth.php, you must fix auth.php to define it.
// Assuming $user is defined by auth.php:
if (!$member && isset($user) && $user['role'] === 'client') {
    die("❌ Error: Your account is not linked to any member record. Please contact support.");
} elseif (!$member) {
    // A secondary check in case require_role allows other roles for some reason,
    // or if the $user variable isn't correctly set in auth.php
    die("❌ Error: Member record missing. Please contact support.");
}


$member_id = $member['id'];
$msg = "";

// ... rest of the file remains the same and is fine ...

// Create upload folder if missing
$uploadDir = __DIR__ . '/upload/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        $msg = "⚠ Only JPG and PNG files are allowed.";
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $msg = "⚠ File is too large. Maximum 5MB allowed.";
    } else {
        // Generate unique name
        $filename = uniqid('proof_', true) . "." . pathinfo($file['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $filename;

        // Move file
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Save to DB
            $stmt = $pdo->prepare("
                INSERT INTO member_photos (member_id, uploaded_by, filename, caption, submitted_date)
                VALUES (?, ?, ?, ?, CURDATE())
            ");
            $caption = $_POST['caption'] ?? 'Proof of payment/income';
            $stmt->execute([$member_id, $user_id, $filename, $caption]);

            $msg = "✅ File uploaded successfully!";
        } else {
            $msg = "❌ Upload failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">