<?php
require 'db.php';

$newPass = "Admin@123"; // or palitan mo agad ng mas malakas
$hash = password_hash($newPass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
$stmt->execute([$hash]);

echo "✅ Admin password has been reset. New password: " . htmlspecialchars($newPass);
