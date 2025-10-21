<?php
require 'db.php';

if (!isset($_GET['token'])) {
    die("Invalid verification link.");
}

$token = $_GET['token'];

$stmt = $pdo->prepare("SELECT id FROM users WHERE email_verification_token = ? AND is_email_verified = 0");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    $update = $pdo->prepare("UPDATE users SET is_email_verified = 1, email_verification_token = NULL WHERE id = ?");
    $update->execute([$user['id']]);
    echo "<h2>Email verified successfully!</h2><p>You can now <a href='index.php'>log in</a>.</p>";
} else {
    echo "<h2>Invalid or expired verification link.</h2>";
}
?>
