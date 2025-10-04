<?php
require_once 'auth.php';
require_role(['staff']);
$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Staff Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="staff">
  <header>
    <h1>Staff Dashboard</h1>
    <nav>
      <a href="staff_dashboard.php">Dashboard</a>
      <a href="record_payment.php">Payments</a>
      <a href="members.php">Members</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </header>

  <div class="dashboard">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Staff)</h2>
    <ul>
      <li>Record Payments</li>
      <li>Manage Assigned Members</li>
      <li>Upload Field Reports</li>
      <li>Notifications</li>
    </ul>
  </div>

  <footer>
    CARD RBI Staff Module
  </footer>
</body>
</html>
