<?php
require_once 'auth.php';
$user = current_user();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$count = $stmt->fetchColumn();

echo json_encode(['count' => $count]);
