<?php
require_once 'auth.php';
require_role(['admin','manager']); // only these roles can send
require_once 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        // Get all users except the sender
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ?");
        $stmt->execute([$_SESSION['user_id']]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Insert notification for each recipient
        $insert = $pdo->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
        foreach ($recipients as $uid) {
            $insert->execute([$uid, $title, $message]);
        }

        $msg = "✅ Notification sent successfully to all users.";
    } else {
        $msg = "⚠️ Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Notification</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#f8fafc; padding:30px; }
form { max-width:500px; background:white; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin:auto; }
h2 { color:#1e3a8a; margin-bottom:20px; text-align:center; }
input, textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e1; border-radius:6px; }
button { background:#2563eb; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; }
button:hover { background:#1d4ed8; }
.msg { margin-bottom:15px; padding:10px; border-radius:6px; }
.success { background:#dcfce7; color:#166534; }
.error { background:#fee2e2; color:#991b1b; }
</style>
</head>
<body>
<form method="post">
  <h2>📢 Send Notification</h2>
  <?php if(!empty($msg)): ?>
    <div class="msg <?= str_contains($msg, '✅') ? 'success' : 'error' ?>"><?= $msg ?></div>
  <?php endif; ?>
  <input type="text" name="title" placeholder="Notification Title" required>
  <textarea name="message" rows="4" placeholder="Notification Message..." required></textarea>
  <button type="submit">Send to All</button>
</form>
</body>
</html>
