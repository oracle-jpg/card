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

$loans = $pdo->query("
  SELECT l.id, m.name, l.amount, l.term_months, l.interest_rate, l.status, l.disbursed_date 
  FROM loans l 
  JOIN members m ON l.member_id = m.id
  ORDER BY l.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan Overview</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { background: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; }
.container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
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
  <h2>💼 Loan Overview</h2>
  <table>
    <tr><th>Member Name</th><th>Amount</th><th>Term (Months)</th><th>Interest (%)</th><th>Status</th><th>Disbursed</th></tr>
    <?php if ($loans): foreach ($loans as $loan): ?>
      <tr>
        <td><?= htmlspecialchars($loan['name']) ?></td>
        <td>₱<?= number_format($loan['amount'],2) ?></td>
        <td><?= $loan['term_months'] ?></td>
        <td><?= $loan['interest_rate'] ?></td>
        <td><?= ucfirst($loan['status']) ?></td>
        <td><?= $loan['disbursed_date'] ?: '—' ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6">No loans found.</td></tr>
    <?php endif; ?>
  </table>
  <a class="back" href="manager_dashboard.php">← Back to Home</a>
</div>
</body>
</html>
