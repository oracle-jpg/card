<?php
require_once 'auth.php';
require_role(['staff','manager']);
$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Loans</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?= $user['role'] ?>">
<header>
  <h1>Loans</h1>
  <nav>
    <a href="<?= $user['role'] === 'staff' ? 'staff_dashboard.php' : 'manager_dashboard.php' ?>">Dashboard</a>
    <a href="index.php?logout=1">Logout</a>
  </nav>
</header>

<div class="dashboard">
  <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</h2>
  <ul>
    <li>View Active Loans</li>
    <li>Approve Loan Requests (Manager)</li>
    <li>Track Member Loan Payments</li>
    <li>Add New Loan (Staff)</li>
  </ul>
</div>

<footer>
  CARD RBI <?= ucfirst($user['role']) ?> Module
</footer>
</body>
</html>
