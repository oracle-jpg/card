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

// Summary
$total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$total_payments = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$total_collected = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();

// Loan details
$query = "
SELECT m.name AS member_name, 
       l.amount AS loan_amount,
       l.status AS loan_status,
       IFNULL(SUM(p.amount), 0) AS total_paid,
       (l.amount - IFNULL(SUM(p.amount),0)) AS balance
FROM loans l
JOIN members m ON l.member_id = m.id
LEFT JOIN payments p ON p.loan_id = l.id
GROUP BY l.id
ORDER BY m.name ASC
";
$details = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports Summary</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body {
  background: #f8fafc;
  font-family: 'Inter', sans-serif;
  color: #1e293b;
  margin: 0;
}
.container {
  max-width: 1000px;
  margin: 50px auto;
  background: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
h2 {
  text-align: center;
  margin-bottom: 25px;
  font-size: 26px;
  color: #1e3a8a;
}
.stats {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 30px;
}
.stat {
  flex: 1;
  min-width: 180px;
  background: #e0f2fe;
  border-left: 6px solid #3b82f6;
  padding: 15px 20px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  color: #1e3a8a;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  margin-bottom: 30px;
}
th, td {
  text-align: left;
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
}
th {
  background: #f1f5f9;
  color: #1e3a8a;
  font-weight: 600;
}
.actions {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-top: 20px;
}
button {
  background: #2563eb;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.3s;
}
button:hover { background: #1d4ed8; }
.btn-download {
  background: #15803d;
}
.btn-download:hover {
  background: #166534;
}
a.back {
  display: inline-block;
  margin-top: 20px;
  text-decoration: none;
  background: #475569;
  color: white;
  padding: 10px 16px;
  border-radius: 6px;
}
a.back:hover { background: #334155; }
</style>
</head>
<body>

<div class="container">
  <h2>📊 Manager Reports Summary</h2>

  <div class="stats">
    <div class="stat">📁 Total Loans: <?= $total_loans ?></div>
    <div class="stat">💸 Total Payments: <?= $total_payments ?></div>
    <div class="stat">🏦 Total Collected: ₱<?= number_format($total_collected, 2) ?></div>
  </div>

  <table>
    <tr>
      <th>Member Name</th>
      <th>Loan Amount</th>
      <th>Total Paid</th>
      <th>Balance</th>
      <th>Status</th>
    </tr>
    <?php foreach ($details as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['member_name']) ?></td>
        <td>₱<?= number_format($row['loan_amount'], 2) ?></td>
        <td>₱<?= number_format($row['total_paid'], 2) ?></td>
        <td>₱<?= number_format($row['balance'], 2) ?></td>
        <td><?= htmlspecialchars($row['loan_status']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="actions">
    <form method="post" action="export_report_pdf.php" target="_blank">
      <button type="submit" class="btn-view">👀 View PDF Report</button>
    </form>

    <form method="post" action="download_report_pdf.php">
      <button type="submit" class="btn-download">📥 Download PDF Report</button>
    </form>
  </div>

  <center><a class="back" href="manager_dashboard.php">← Back to Dashboard</a></center>
</div>

</body>
</html>
