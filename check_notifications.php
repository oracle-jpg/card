<?php
require_once 'auth.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$sql = "SELECT * FROM notifications
        WHERE 
          (target_role = :role OR target_role = 'all' OR target_user_id = :user_id)
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['role' => $user_role, 'user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notifications);
?>
