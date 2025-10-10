<?php
require_once 'auth.php';
require_role(['admin', 'manager']);
require_once 'db.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_role = $_POST['target_role'];

    if ($title && $message) {
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, target_role) VALUES (?, ?, ?)");
        $stmt->execute([$title, $message, $target_role]);
        $msg = "✅ Notification posted successfully.";
    } else {
        $msg = "⚠ Please fill all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Create Notification</title>
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b;padding:30px;}
form{background:#fff;padding:20px;border-radius:10px;max-width:500px;margin:auto;box-shadow:0 3px 8px rgba(0,0,0,0.08);}
input,textarea,select{width:100%;padding:10px;margin:8px 0;border:1px solid #cbd5e1;border-radius:6px;}
button{background:#2563eb;color:#fff;border:none;padding:10px 15px;border-radius:6px;cursor:pointer;}
button:hover{background:#1d4ed8;}
.msg{margin-bottom:10px;color:#16a34a;}
</style>
</head>
<body>
<h2 align="center">📰 Create Notification</h2>
<form method="POST">
  <?php if ($msg) echo "<p class='msg'>$msg</p>"; ?>
  <label>Title</label>
  <input name="title" required>
  <label>Message</label>
  <textarea name="message" rows="4" required></textarea>
  <label>Target Audience</label>
  <select name="target_role" required>
    <option value="all">All</option>
    <option value="manager">Managers</option>
    <option value="staff">Staff</option>
    <option value="client">Clients</option>
  </select>
  <button type="submit">Post Notification</button>
</form>
</body>
</html>
