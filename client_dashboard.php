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

// Get linked member record — auto-create if missing
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();

if (!$member) {
    // Auto-create member record if missing
    $stmt = $pdo->prepare("INSERT INTO members (user_id, name, status, created_at) VALUES (?, ?, 'active', NOW())");
    $stmt->execute([$user_id, $user['full_name']]);

    // Re-fetch
    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
}

$member_id = $member ? $member['id'] : null;

// Loan summary
$total_loan_stmt = $pdo->prepare("SELECT SUM(l.amount) AS total_loan FROM loans l WHERE l.member_id = ?");
$total_loan_stmt->execute([$member_id]);
$total_loan = $total_loan_stmt->fetchColumn() ?? 0;

$total_paid_stmt = $pdo->prepare("SELECT SUM(p.amount) AS total_paid FROM payments p 
                                 JOIN loans l ON p.loan_id = l.id 
                                 WHERE l.member_id = ?");
$total_paid_stmt->execute([$member_id]);
$total_paid = $total_paid_stmt->fetchColumn() ?? 0;

$outstanding = $total_loan - $total_paid;

// Recent payments
$stmt = $pdo->prepare("
    SELECT p.payment_date, p.amount, p.method 
    FROM payments p
    JOIN loans l ON p.loan_id = l.id
    WHERE l.member_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 5
");
$stmt->execute([$member_id]);
$recent_payments = $stmt->fetchAll();

// Latest proof
$stmt = $pdo->prepare("SELECT * FROM member_photos WHERE member_id = ? ORDER BY submitted_date DESC LIMIT 1");
$stmt->execute([$member_id]);
$latest_proof = $stmt->fetch();

// Recent activity
$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Client Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
body { display:flex; background:#f8fafc; color:#1e293b; }

/* Sidebar */
.sidebar {
  width:230px; background:#0f172a; color:#fff; min-height:100vh;
  padding:25px 20px; display:flex; flex-direction:column;
}
.sidebar h2 { font-size:20px; margin-bottom:30px; }
.sidebar a {
  color:#e2e8f0; text-decoration:none; padding:10px;
  margin-bottom:8px; border-radius:6px; display:block; transition:0.3s;
}
.sidebar a:hover { background:#1e293b; color:#fff; }
.logout {
  margin-top:auto; background:#dc2626; color:#fff;
  text-align:center; padding:10px; border-radius:6px;
  text-decoration:none;
}
.logout:hover { background:#b91c1c; }

/* Main */
.main { flex:1; padding:30px 40px; }
header {
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:25px;
}
header h1 { font-size:24px; font-weight:600; color:#1e3a8a; }
.profile {
  background:#e0f2fe; color:#1e3a8a;
  padding:8px 15px; border-radius:8px; font-weight:500;
}

/* Summary cards */
.cards {
  display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:20px; margin-bottom:25px;
}
.card {
  background:#fff; padding:20px; border-radius:10px;
  box-shadow:0 3px 8px rgba(0,0,0,0.08);
}
.card h3 { color:#475569; font-size:15px; margin-bottom:10px; }
.card p { font-size:22px; font-weight:700; color:#1e3a8a; }

/* Table */
.table-card table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; }
th { background:#f1f5f9; }

/* Proof and logs */
.section {
  background:#fff; padding:20px; border-radius:10px;
  box-shadow:0 3px 8px rgba(0,0,0,0.08); margin-bottom:25px;
}
.section h3 { margin-bottom:10px; font-size:18px; color:#1e3a8a; }
.logs ul { list-style:none; }
.logs li {
  border-bottom:1px solid #e5e7eb; padding:8px 0;
  font-size:14px; display:flex; justify-content:space-between;
  color:#334155;
}
.btn {
  background:#2563eb; color:#fff; padding:8px 14px;
  border:none; border-radius:6px; cursor:pointer;
  text-decoration:none;
}
.btn:hover { background:#1d4ed8; }
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
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <h2>Client Panel</h2>
  <a href="client_dashboard.php">📊 Home</a>
  <a href="my_loans.php">💼 Loans</a>
  <a href="my_payments.php">💰 Payments</a>
  <a href="upload_photo.php">📸 Upload Proof</a>
  <a href="my_history.php">📜 History</a>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<!-- Main -->
<main class="main">
  <header>
  <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?></h1>
  <div style="display:flex;align-items:center;gap:20px;">
    <!-- 🔔 Notification Bell -->
    <div class="notif-container">
      <div class="bell" onclick="toggleDropdown()">🔔
        <?php
          $count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
          $count->execute([$user['id']]);
          $unread = $count->fetchColumn();
          if ($unread > 0) echo "<span class='badge'>$unread</span>";
        ?>
      </div>
      <div id="notifDropdown" class="dropdown">
        <?php
          $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
          $stmt->execute([$user['id']]);
          $notif_list = $stmt->fetchAll();

          if ($notif_list) {
            foreach ($notif_list as $n) {
              echo "<div class='notif-item' onclick=\"viewNotif(".$n['id'].")\">
                      <strong>".htmlspecialchars($n['title'])."</strong>
                      <p>".htmlspecialchars(substr($n['message'], 0, 60))."...</p>
                    </div>";
            }
            echo "<a href='notifications.php' class='view-all'>View All Notifications</a>";
          } else {
            echo "<p class='empty'>No notifications</p>";
          }
        ?>
      </div>
    </div>

    <!-- 👤 Profile -->
    <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
  </div>
</header>

  <!-- Summary Cards -->
  <div class="cards">
    <div class="card"><h3>Total Loan</h3><p>₱<?= number_format($total_loan, 2) ?></p></div>
    <div class="card"><h3>Total Paid</h3><p>₱<?= number_format($total_paid, 2) ?></p></div>
    <div class="card"><h3>Outstanding Balance</h3><p>₱<?= number_format($outstanding, 2) ?></p></div>
    <div class="card">
      <h3>Last Proof Upload</h3>
      <p><?= $latest_proof ? htmlspecialchars(date('M d, Y', strtotime($latest_proof['submitted_date']))) : 'No proof yet' ?></p>
    </div>
  </div>

  <!-- Recent Payments -->
  <div class="section table-card">
    <h3>Recent Payments</h3>
    <table>
      <tr><th>Date</th><th>Amount</th><th>Method</th></tr>
      <?php if ($recent_payments): ?>
        <?php foreach ($recent_payments as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['payment_date']) ?></td>
            <td>₱<?= number_format($p['amount'],2) ?></td>
            <td><?= htmlspecialchars($p['method']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="3">No recent payments.</td></tr>
      <?php endif; ?>
    </table>
    <br>
    <a href="my_payments.php" class="btn">View All Payments</a>
  </div>

  <!-- Activity Logs -->
  <div class="section logs">
    <h3>Recent Activity</h3>
    <ul>
      <?php if ($logs): ?>
        <?php foreach ($logs as $log): ?>
          <li>
            <span><?= htmlspecialchars($log['action']) ?></span>
            <span><?= htmlspecialchars(date('M d, Y', strtotime($log['created_at']))) ?></span>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li>No recent activity.</li>
      <?php endif; ?>
    </ul>
  </div>
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
</main>
</body>
</html>
