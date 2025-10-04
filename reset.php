<?php
require 'db.php';
$newPassword = 'Admin@123'; // pwede mong palitan
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE username='admin'");
$stmt->execute([$hash]);

echo "Password reset OK. New password: $newPassword";
?>