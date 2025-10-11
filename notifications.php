<?php
require_once 'auth.php';
require_once 'db.php';
$user = current_user();

// Fetch all notifications (latest first)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Mark notification as read via AJAX
if (isset($_GET['mark_read'])) {
    $id = intval($_GET['mark_read']);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    exit('ok');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#f8fafc; margin:0; padding:30px; color:#1e293b; }
.container { max-width:800px; margin:auto; background:white; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.08); padding:25px; }
h2 { color:#1e3a8a; margin-bottom:20px; }
.notice { border-bottom:1px solid #e5e7eb; padding:12px 0; }
.notice.unread { background:#eff6ff; }
.notice:last-child { border:none; }
.notice strong { color:#1e3a8a; display:block; }
.notice small { color:#64748b; }
</style>
</head>
<body>
<div class="container">
  <h2>All Notifications</h2>
  <?php if($notifications): foreach($notifications as $n): ?>
    <div class="notice <?= !$n['is_read'] ? 'unread':'' ?>" onclick="markRead(<?= $n['id'] ?>)">
      <strong><?= htmlspecialchars($n['title']) ?></strong>
      <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
      <small>📅 <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></small>
    </div>
  <?php endforeach; else: ?>
    <p>No notifications found.</p>
  <?php endif; ?>
</div>

<script>
function markRead(id) {
  fetch('notifications.php?mark_read=' + id)
    .then(() => console.log('Marked as read'));
}
</script>
</body>
</html>