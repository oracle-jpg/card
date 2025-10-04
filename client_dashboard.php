<?php
require_once 'auth.php';
require_role(['client']);
$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Client Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="client">
  <header>
    <h1>Client Dashboard</h1>
    <nav>
      <a href="client_dashboard.php">Dashboard</a>
      <a href="my_loans.php">Loans</a>
      <a href="my_payments.php">Payments</a>
      <a href="my_history.php">History</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </header>

  <div class="dashboard">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Client)</h2>
    <ul>
      <li>Upload Monthly Proof</li>
      <li>View My Loans</li>
      <li>Make Payment</li>
      <li>My History</li>
    </ul>
  </div>

  <footer>
    Ito ang bangko natin.
  </footer>
</body>
</html>
