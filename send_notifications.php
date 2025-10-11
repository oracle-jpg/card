<?php
require_once 'auth.php';
require_role(['admin', 'manager']); // Only admins/managers can send

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_role = $_POST['target_role'] ?? 'all';
    $target_user_id = !empty($_POST['target_user_id']) ? $_POST['target_user_id'] : NULL;

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, target_role, target_user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $title, $message, $target_role, $target_user_id]);
        echo "✅ Notification sent successfully!";
    } else {
        echo "⚠️Title and message are required.";
    }
}
?>

<!-- Optional announcement form (place this in admin_dashboard.php if you like) -->
<form method="POST" action="send_notifications.php" style="margin-top:20px;">
  <input type="text" name="title" placeholder="Announcement Title" required><br><br>
  <textarea name="message" placeholder="Announcement Message" required></textarea><br><br>
  <label>Target Audience:</label><br>
  <select name="target_role">
    <option value="all">All Users</option>
    <option value="admin">Admins</option>
    <option value="manager">Managers</option>
    <option value="staff">Staff</option>
    <option value="client">Clients</option>
  </select><br><br>
  <button type="submit">Send Notification</button>
</form>
