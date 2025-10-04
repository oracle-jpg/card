<?php
require_once 'auth.php';
require_role(['manager']);
$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manager Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="manager">
  <header>
    <h1>Manager Dashboard</h1>
    <nav>
      <a href="manager_dashboard.php">Dashboard</a>
      <a href="reports.php">Reports</a>
      <a href="loans.php">Loans</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </header>

  <div class="dashboard">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Manager)</h2>
    <ul>
      <li>View Analytics & Reports</li>
      <li>Approve Loans</li>
      <li>Monitor Staff / Members</li>
      <li>View Notifications</li>
    </ul>
  </div>

  <footer>
    CARD RBI Manager Module
  </footer>
</body>
</html>
