<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch notifications for current user or broadcast
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->execute([$user['id']]);
$notifications = $notif_stmt->fetchAll();

// Count unread notifications
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
$count_stmt->execute([$user['id']]);
$unread_count = $count_stmt->fetchColumn();

// Check role (admin / operations manager only)
if ($user['role'] !== 'admin') {
  echo "<script>alert('Access denied!'); window.location='index.php';</script>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Operations Manager Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { display: flex; background: #f8fafc; color: #1e293b; }

    /* Sidebar */
    .sidebar {
      width: 230px;
      background: #0f172a;
      color: #fff;
      min-height: 100vh;
      padding: 20px;
      position: fixed;
    }
    .sidebar h2 {
      font-size: 20px;
      margin-bottom: 30px;
      text-align: center;
    }
    .sidebar a {
      display: block;
      color: #e2e8f0;
      padding: 10px;
      margin: 8px 0;
      border-radius: 6px;
      text-decoration: none;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: #1e293b;
      color: #fff;
    }

    /* Main */
    .main {
      flex: 1;
      margin-left: 230px;
      padding: 30px;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    header h1 {
      font-size: 22px;
      font-weight: 600;
    }
    .profile {
      background: #2563eb;
      color: white;
      padding: 8px 15px;
      border-radius: 6px;
    }

    .card {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .card h3 {
      margin-bottom: 15px;
      font-size: 18px;
      font-weight: 600;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    table th, table td {
      text-align: left;
      padding: 10px;
      border-bottom: 1px solid #e5e7eb;
    }
    table th {
      background: #f1f5f9;
    }
    /* 🔔 Notification Bell */
.notif-container { position: relative; display: inline-block; cursor: pointer; }
.bell { font-size: 22px; position: relative; }
.badge {
  position: absolute; top: -6px; right: -8px;
  background: #ef4444; color: #fff; font-size: 12px;
  padding: 2px 5px; border-radius: 50%;
}
.dropdown {
  display: none;
  position: absolute;
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 280px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 999;
  max-height: 350px;
  overflow-y: auto;
}
.notif-item {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
}
.notif-item:hover { background: #f1f5f9; }
.view-all {
  display: block;
  text-align: center;
  padding: 10px;
  background: #2563eb;
  color: white;
  text-decoration: none;
  border-radius: 0 0 8px 8px;
}
.view-all:hover { background: #1d4ed8; }
.empty { text-align: center; color: #64748b; padding: 15px; }

  </style>
</head>
<script>
function toggleDropdown() {
  const dd = document.getElementById('notifDropdown');
  dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
}

function viewNotif(id) {
  fetch('notifications.php?mark_read=' + id)
    .then(() => location.href = 'notifications.php');
}

window.onclick = function(e) {
  if (!e.target.closest('.notif-container')) {
    document.getElementById('notifDropdown').style.display = 'none';
  }
}
</script>

<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Operations Manager</h2>
    <a href="admin_dashboard.php">🏠 Home</a>
    <a href="manage_members.php">👥 Manage Members</a>
    <a href="manage_loans.php">💼 Manage Loans</a>
    <a href="record_payments.php">💰 Record Payments</a>
    <a href="generate_reports.php">📊 Reports</a>
    <a href="create_user.php">➕ Create Staff / Manager</a>
    <a href="index.php?logout=1">🚪 Logout</a>
  </aside>

  <!-- Main -->
  <main class="main">
  <header>
  <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?></h1>
  <div style="display:flex;align-items:center;gap:20px;">
    
    <!-- 🔔 Notification Bell -->
    <div class="notif-container">
      <div class="bell" onclick="toggleDropdown()">🔔
        <?php if ($unread_count > 0): ?>
          <span class="badge"><?= $unread_count ?></span>
        <?php endif; ?>
      </div>
      <div id="notifDropdown" class="dropdown">
        <?php if ($notifications): ?>
          <?php foreach ($notifications as $n): ?>
            <div class="notif-item" onclick="viewNotif(<?= $n['id'] ?>)">
              <strong><?= htmlspecialchars($n['title']) ?></strong>
              <p><?= htmlspecialchars(substr($n['message'], 0, 60)) ?>...</p>
            </div>
          <?php endforeach; ?>
          <a href="notifications.php" class="view-all">View All Notifications</a>
        <?php else: ?>
          <p class="empty">No notifications</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- 👤 Profile -->
    <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
  </div>
</header>

    <div class="card">
      <h3>📊 System Overview</h3>
      <table>
        <tr><th>Category</th><th>Total</th></tr>
        <tr><td>Total Clients</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?></td></tr>
        <tr><td>Total Staff</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(); ?></td></tr>
        <tr><td>Total Managers</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn(); ?></td></tr>
        <tr><td>Total Loans</td><td><?= $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(); ?></td></tr>
        <tr><td>Total Payments</td><td><?= $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(); ?></td></tr>
      </table>
    </div>

    <div class="card">
      <h3>🧾 Recent System Activity</h3>
      <table>
        <tr><th>Date</th><th>Action</th><th>User</th></tr>
        <?php
        $logs = $pdo->query("
          SELECT a.created_at, a.action, u.full_name 
          FROM audit_logs a 
          LEFT JOIN users u ON a.user_id = u.id 
          ORDER BY a.created_at DESC 
          LIMIT 5
        ")->fetchAll();

        if ($logs) {
          foreach ($logs as $log) {
            echo "<tr>
                    <td>{$log['created_at']}</td>
                    <td>{$log['action']}</td>
                    <td>{$log['full_name']}</td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='3'>No recent activity.</td></tr>";
        }
        ?>
      </table>
    </div>
  </main>

</body>
</html>
