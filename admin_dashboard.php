<?php
require_once 'auth.php';
require_role(['admin']);
$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Operations Manager Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="admin">
  <header>
    <h1>Operations Manager Panel</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="create_user.php">Add Staff/Manager</a>
      <a href="reports.php">Reports</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </header>

  <div class="dashboard">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Operations Manager)</h2>
    <ul>
      <li>Manage Members</li>
      <li>Manage Loans</li>
      <li>Record Payments</li>
      <li>Upload Member Photos</li>
      <li>Generate Reports</li>
    </ul>
  </div>

  <footer>
    CARD RBI Operations Manager Module
  </footer>
</body>
</html>
