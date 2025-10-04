<?php
require_once 'auth.php';
$user = current_user();
if(!in_array($user['role'], ['client','staff'])) header("Location: index.php");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payments</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?= $user['role'] ?>">
<header>
  <h1>Payments</h1>
  <nav>
    <?php if($user['role']=='client'): ?>
      <a href="client_dashboard.php">Dashboard</a>
    <?php else: ?>
      <a href="staff_dashboard.php">Dashboard</a>
    <?php endif; ?>
    <a href="index.php?logout=1">Logout</a>
  </nav>
</header>

<div class="dashboard">
  <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</h2>
  <ul>
    <li>View Payment History</li>
    <li>Make New Payment</li>
    <li>Download Payment Receipt</li>
    <li>Check Outstanding Balance</li>
  </ul>
</div>

<footer>
  CARD RBI <?= ucfirst($user['role']) ?> Module
</footer>
</body>
</html>
