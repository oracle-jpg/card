<?php
// notification_bell.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) exit;

// Get user role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$role = $user['role'] ?? '';

// Fetch unread + read notifications visible for this role/user
$stmt = $pdo->prepare("
    SELECT id, title, message, is_read, created_at
    FROM notifications
    WHERE (target_role = 'all' 
           OR target_role = ? 
           OR target_user_id = ?)
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$role, $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="notif-dropdown">
  <button class="notif-btn" onclick="toggleNotif()">
    🔔
    <?php 
      $unread = array_filter($notifications, fn($n) => !$n['is_read']);
      if (count($unread) > 0): ?>
        <span class="notif-count"><?= count($unread) ?></span>
    <?php endif; ?>
  </button>

  <div class="notif-list" id="notifList">
    <?php if ($notifications): ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" 
             onclick="window.location.href='view_notification.php?id=<?= $n['id'] ?>'">
          <strong><?= htmlspecialchars($n['title']) ?></strong>
          <p><?= htmlspecialchars(substr($n['message'], 0, 50)) ?>...</p>
          <small><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty">No notifications</p>
    <?php endif; ?>
  </div>
</div>

<style>
.notif-dropdown { position: relative; display: inline-block; margin-left: 300px; }
.notif-btn { background: none; border: none; cursor: pointer; font-size: 20px; position: relative; }
.notif-count {
  background: #ef4444; color: #fff; font-size: 12px;
  border-radius: 50%; padding: 2px 6px; position: absolute; top: -6px; right: -8px;
}
.notif-list {
  display: none; position: absolute; right: 0; top: 30px;
  background: #fff; border: 1px solid #e5e7eb; width: 300px;
  border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); z-index: 100;
}
.notif-item { padding: 10px; border-bottom: 1px solid #e5e7eb; cursor: pointer; }
.notif-item.unread { background: #e0f2fe; }
.notif-item:hover { background: #f1f5f9; }
.notif-list p.empty { padding: 10px; text-align: center; color: #6b7280; }
</style>

<script>
function toggleNotif(){
  const list = document.getElementById('notifList');
  list.style.display = list.style.display === 'block' ? 'none' : 'block';
}
</script>
