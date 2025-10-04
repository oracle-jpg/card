<?php
require 'db.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute(['admin']);
$user = $stmt->fetch();

if (!$user) {
    echo "⚠️ No admin user found in DB.<br>";
} else {
    echo "✅ Found admin in DB.<br>";
    echo "Stored hash: " . $user['password_hash'] . "<br>";

    if (password_verify('Admin@123', $user['password_hash'])) {
        echo "🎉 Password Admin@123 is valid!";
    } else {
        echo "❌ Password Admin@123 does not match hash!";
    }
}
