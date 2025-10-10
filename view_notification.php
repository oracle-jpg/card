<?php
require_once 'db.php';
session_start();

if (!isset($_GET['id'])) die("No notification selected.");
$id = intval($_GET['id']);

// Mark as read
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
$stmt->execute([$id]);
$notif = $stmt->fetch();
if (!$notif) die("Notification not found.");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($notif['title']) ?></title>
  <style>
  body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 40px; color: #1e293b; }
  .container { background: #fff; padding: 25px; border-radius: 10px; max-width: 600px; margin:auto; box-shadow:0 3px 8px rgba(0,0,0,0.08);}
  h1 { color:#1e3a8a; margin-bottom:10px; }
  small { color:#64748b; }
  p { margin-top:15px; line-height:1.5; }
  a { color:#2563eb; text-decoration:none; }
  </style>
</head>
<body>
<div class="container">
  <h1><?= htmlspecialchars($notif['title']) ?></h1>
  <small>Posted on <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></small>
  <p><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
  <a href="javascript:history.back()">⬅ Back</a>
</div>
</body>
</html>
