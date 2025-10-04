<?php
session_start();
require 'db.php';

// Check login & role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'manager') {
    echo "Access denied.";
    exit;
}

// Overview
$totalStaff = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$activeLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status='ongoing'")->fetchColumn();

$sql = "
SELECT u.full_name AS name,
       COUNT(DISTINCT m.id) AS clients,
       COUNT(DISTINCT l.id) AS loans,
       COALESCE(ROUND(SUM(CASE WHEN p.amount IS NOT NULL THEN 1 ELSE 0 END)/NULLIF(COUNT(l.id),0)*100,0),0) AS rate
FROM users u
LEFT JOIN members m ON m.created_by = u.id
LEFT JOIN loans l ON l.member_id = m.id
LEFT JOIN payments p ON p.loan_id = l.id
WHERE u.role='staff'
GROUP BY u.id
";
$staffPerformance = $pdo->query($sql)->fetchAll();


// New clients this month
$newClients = $pdo->query("SELECT COUNT(*) FROM members WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();

// Loan trend (6 months)
$loanTrend = $pdo->query("
SELECT DATE_FORMAT(disbursed_date, '%b') AS month, SUM(amount) AS total
FROM loans
WHERE disbursed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY YEAR(disbursed_date), MONTH(disbursed_date)
ORDER BY MIN(disbursed_date)
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manager Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: 'Inter', sans-serif; background:#f4f6f9; }
    .sidebar { width:220px; height:100vh; background:#004D99; color:#fff; position:fixed; padding:20px; }
    .sidebar a { color:#fff; display:block; margin:12px 0; text-decoration:none; }
    .sidebar a:hover { text-decoration:underline; }
    .main { margin-left:240px; padding:20px; }
    .card { border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>Microfinance Co.</h2>
    <a href="manager_dashboard.php">📊 Dashboard</a>
    <a href="manage_staff.php">👨‍💼 Staff</a>
    <a href="manage_clients.php">👥 Clients</a>
    <a href="manage_loans.php">💰 Loans</a>
    <a href="reports.php">📑 Reports</a>
    <a href="index.php?logout=1">🚪 Logout</a>
  </div>

  <div class="main">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Manager)</h2>

    <!-- Overview -->
    <div class="row">
      <div class="col-md-4"><div class="card p-3 text-center"><h6>Total Staff</h6><h3><?= $totalStaff ?></h3></div></div>
      <div class="col-md-4"><div class="card p-3 text-center"><h6>Total Clients</h6><h3><?= $totalClients ?></h3></div></div>
      <div class="col-md-4"><div class="card p-3 text-center"><h6>Active Loans</h6><h3><?= $activeLoans ?></h3></div></div>
    </div>

    <!-- Staff Performance Chart -->
    <div class="card p-3">
      <h4>Staff Performance</h4>
      <canvas id="staffChart" height="100"></canvas>
    </div>

    <!-- Loan Analytics -->
    <div class="row">
      <div class="col-md-6">
        <div class="card p-3 text-center">
          <h5>New Clients This Month</h5>
          <h3><?= $newClients ?></h3>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3">
          <h5>Loan Disbursement Trend (6 months)</h5>
          <canvas id="loanChart" height="100"></canvas>
        </div>
      </div>
    </div>
  </div>

<script>
  // Staff Performance Bar Chart
  const staffData = {
    labels: <?= json_encode(array_column($staffPerformance, 'name')) ?>,
    datasets: [{
      label: 'Repayment Rate %',
      data: <?= json_encode(array_column($staffPerformance, 'rate')) ?>,
      backgroundColor: '#4CAF50'
    }]
  };
  new Chart(document.getElementById('staffChart'), {
    type: 'bar',
    data: staffData,
    options: { scales: { y: { beginAtZero:true, max:100 } } }
  });

  // Loan Trend Line Chart
  const loanData = {
    labels: <?= json_encode(array_column($loanTrend, 'month')) ?>,
    datasets: [{
      label: 'Loan Amount',
      data: <?= json_encode(array_column($loanTrend, 'total')) ?>,
      borderColor: '#004D99',
      backgroundColor: 'rgba(0,77,153,0.2)',
      tension: 0.3,
      fill: true
    }]
  };
  new Chart(document.getElementById('loanChart'), {
    type: 'line',
    data: loanData,
    options: { scales: { y: { beginAtZero:true } } }
  });
</script>
</body>
</html>
