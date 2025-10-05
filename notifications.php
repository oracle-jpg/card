<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
  echo "<script>alert('Access denied!'); window.location='index.php';</script>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title']);
  $message = trim($_POST['message']);
  $target_role = $_POST['target_role'];

  if ($title && $message) {
    if ($target_role === 'all') {
      $pdo->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)")->execute([$title, $message]);
    } else {
      $users = $pdo->prepare("SELECT id FROM users WHERE role = ?");
      $users->execute([$target_role]);
      foreach ($users as $u) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$u['id'], $title, $message]);
      }
    }
    echo "<script>alert('✅ Notification sent successfully!');</script>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; }
.container { max-width: 600px; margin: 40px auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { margin-bottom: 20px; }
label { display: block; margin-top: 10px; }
input, textarea, select {
  width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #cbd5e1; border-radius: 8px;
}
button {
  margin-top: 20px; background: #2563eb; color: #fff; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer;
}
button:hover { background: #1d4ed8; }
.back { display: inline-block; margin-top: 20px; text-decoration: none; background: #475569; color: white; padding: 10px 16px; border-radius: 6px; }
</style>
</head>
<body>
<div class="container">
  <h2>🔔 Send Notification</h2>
  <form method="post">
    <label>Target Group</label>
    <select name="target_role" required>
      <option value="all">All Users</option>
      <option value="staff">Staff</option>
      <option value="client">Clients</option>
    </select>
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Message</label>
    <textarea name="message" rows="5" required></textarea>
    <button type="submit">Send</button>
  </form>
  <a class="back" href="manager_dashboard.php">← Back to Dashboard</a>
</div>
</body>
</html>
