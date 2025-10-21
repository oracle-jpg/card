<?php
session_start();
require 'auth.php';
require_once 'db.php';

// Check if admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if ($user['role'] !== 'admin') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}

// Fetch members and staff
$members = $pdo->query("SELECT * FROM members WHERE ci_status = 'pending'")->fetchAll();
$staff = $pdo->query("SELECT id, full_name FROM users WHERE role='staff'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    $staff_id = $_POST['staff_id'];

    $pdo->prepare("INSERT INTO credit_investigations (member_id, assigned_to) VALUES (?, ?)")->execute([$member_id, $staff_id]);
    $pdo->prepare("UPDATE members SET ci_status = 'under_review' WHERE id = ?")->execute([$member_id]);

    echo "<script>alert('✅ CI assigned successfully!'); location.href='admin_ci_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assign Credit Investigation - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
body { display: flex; background: #f8fafc; color: #1e293b; }
.sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; position: fixed; }
.logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; border-radius: 8px; }
.logo-box img { height: 60px; border-radius: 6px; }
.sidebar a { display: block; color: #e2e8f0; padding: 10px; margin: 8px 0; border-radius: 6px; text-decoration: none; transition: 0.3s; }
.sidebar a:hover { background: #1e293b; color: #fff; }
.main { flex: 1; margin-left: 230px; padding: 30px; }
header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
header h1 { font-size: 22px; font-weight: 600; color: #1e3a8a; }
.profile { background: #2563eb; color: #fff; padding: 8px 14px; border-radius: 6px; font-weight: 500; }
.card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }

table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; border-radius: 8px; overflow: hidden; }
table th, table td { text-align: left; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
table th { background: #f1f5f9; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 13px; }
table tbody tr:hover { background-color: #f8fafc; }

form select, form button {
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-size: 14px;
}
form button {
  background: #2563eb;
  color: white;
  cursor: pointer;
  transition: 0.2s;
  border: none;
}
form button:hover { background: #1d4ed8; }
.empty-msg { text-align: center; color: #64748b; padding: 15px; font-style: italic; }

@media (max-width: 768px) {
  .sidebar { width: 100%; height: auto; position: relative; }
  .main { margin-left: 0; padding: 20px; }
  table { font-size: 12px; }
}
</style>
</head>

<body>
<aside class="sidebar">
  <div class="logo-box">
    <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Logo">
  </div>
  <a href="admin_dashboard.php">🏠 Home</a>
  <a href="manage_members.php">👥 Manage Members</a>
  <a href="manage_loans.php">💼 Manage Loans</a>
  <a href="generate_reports.php">📊 Reports</a>
  <a href="create_user.php">➕ Create Personnel</a>
  <a href="admin_ci_list.php" style="background:#1e293b;">📝 Credit Investigation</a>
</aside>

<main class="main">
  <header>
    <h1>📝 Assign Credit Investigation</h1>
    <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
  </header>

  <div class="card">
    <h3>Pending Clients for CI (<?= count($members) ?>)</h3>
    <?php if ($members): ?>
    <form method="POST" style="margin-top:15px;">
      <label style="font-weight:600;">Select Member:</label><br>
      <select name="member_id" required style="width:100%;margin-bottom:15px;">
        <?php foreach ($members as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label style="font-weight:600;">Assign to Staff:</label><br>
      <select name="staff_id" required style="width:100%;margin-bottom:15px;">
        <?php foreach ($staff as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Assign CI Task</button>
    </form>
    <?php else: ?>
      <p class="empty-msg">✅ No clients are currently pending for Credit Investigation.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>🕒 Recently Assigned CI Tasks</h3>
    <table>
      <thead>
        <tr><th>Member Name</th><th>Assigned Staff</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
      <?php
        $recent = $pdo->query("
          SELECT ci.*, m.name AS member_name, u.full_name AS staff_name
          FROM credit_investigations ci
          JOIN members m ON ci.member_id = m.id
          LEFT JOIN users u ON ci.assigned_to = u.id
          ORDER BY ci.created_at DESC LIMIT 10
        ")->fetchAll();
        if ($recent):
          foreach ($recent as $r):
      ?>
        <tr>
          <td><?= htmlspecialchars($r['member_name']) ?></td>
          <td><?= htmlspecialchars($r['staff_name'] ?? 'N/A') ?></td>
          <td><?= ucfirst($r['ci_status']) ?></td>
          <td><?= htmlspecialchars(date("M d, Y", strtotime($r['created_at']))) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="4" class="empty-msg">No recent CI assignments found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
