<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

// Get logged-in user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Allow only manager
if ($user['role'] !== 'manager') {
  echo "<script>alert('Access denied!'); window.location='index.php';</script>";
  exit;
}

// Fetch staff performance data
$stmt = $pdo->query("
  SELECT u.full_name, COUNT(p.id) AS total_collections, SUM(p.amount) AS total_amount, MAX(p.created_at) AS last_activity
  FROM users u
  LEFT JOIN payments p ON p.collected_by = u.id
  WHERE u.role = 'staff'
  GROUP BY u.id
  ORDER BY total_collections DESC
");
$staff_data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>Staff Performance</title>
<link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
<style>
body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; }
.container { max-width: 1000px; margin: 40px auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
th { background: #f1f5f9; }
.back { display: inline-block; margin-top: 20px; text-decoration: none; background: #2563eb; color: white; padding: 10px 16px; border-radius: 6px; }
.back:hover { background: #1d4ed8; }
</style>
</head>
<body>
<div class="container">
  <h2>👥 Staff Performance</h2>
  <table>
    <tr><th>Staff Name</th><th>Total Payments</th><th>Total Amount</th><th>Last Activity</th></tr>
    <?php if ($staff_data): foreach ($staff_data as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= $s['total_collections'] ?></td>
        <td>₱<?= number_format($s['total_amount'],2) ?></td>
        <td><?= $s['last_activity'] ?: 'No activity' ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="4">No staff data available.</td></tr>
    <?php endif; ?>
  </table>
  <a class="back" href="manager_dashboard.php">← Back to Dashboard</a>
</div>
</body>
</html>
