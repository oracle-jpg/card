<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get linked member record (client)
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_id = $member ? $member['id'] : null;

// Fetch client loans
$stmt = $pdo->prepare("SELECT * FROM loans WHERE member_id = ?");
$stmt->execute([$member_id]);
$loans = $stmt->fetchAll();

// Fetch client payments
$stmt = $pdo->prepare("
    SELECT p.payment_date, p.amount, p.method
    FROM payments p
    JOIN loans l ON p.loan_id = l.id
    WHERE l.member_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$member_id]);
$payments = $stmt->fetchAll();

// Fetch latest proof
$stmt = $pdo->prepare("SELECT * FROM member_photos WHERE member_id = ? ORDER BY submitted_date DESC LIMIT 1");
$stmt->execute([$member_id]);
$latest_proof = $stmt->fetch();

// Fetch audit history
$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Client Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { display: flex; background: #f8fafc; color: #1e293b; }

    /* Sidebar */
    .sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; }
    .sidebar h2 { font-size: 20px; margin-bottom: 30px; }
    .sidebar a { display: block; color: #e2e8f0; padding: 10px; margin: 8px 0; border-radius: 6px; text-decoration: none; transition: 0.3s; }
    .sidebar a:hover { background: #1e293b; color: #fff; }

    /* Main */
    .main { flex: 1; padding: 30px; }
    header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    header h1 { font-size: 22px; font-weight: 600; }
    .quick-actions button { background: #2563eb; border: none; padding: 10px 16px; margin-right: 10px; border-radius: 6px; color: #fff; cursor: pointer; }

    .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .card h3 { margin-bottom: 15px; font-size: 18px; font-weight: 600; }

    table { width: 100%; border-collapse: collapse; }
    table th, table td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
    .status-paid { color: #16a34a; font-weight: 600; }

    .upload-box { text-align: center; padding: 25px; border: 2px dashed #94a3b8; border-radius: 10px; color: #64748b; }
    .upload-box button { margin-top: 10px; background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }

    .history ul { list-style: none; }
    .history li { margin: 10px 0; display: flex; justify-content: space-between; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Client Panel</h2>
    <a href="client_dashboard.php">📊 Dashboard</a>
    <a href="my_loans.php">💼 Loans</a>
    <a href="my_payments.php">💰 Payments</a>
    <a href="upload_photo.php">⬆ Upload Proof</a>
    <a href="my_history.php">📜 History</a>
    <a href="index.php?logout=1">🚪 Logout</a>
  </aside>

  <!-- Main -->
  <main class="main">
    <header>
      <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?></h1>
      <div class="profile">👤 <?= htmlspecialchars($user['role']) ?></div>
    </header>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <button onclick="location.href='make_payment.php'">💰 Make Payment</button>
      <button onclick="location.href='my_loans.php'">📄 View My Loans</button>
    </div>

    <!-- Loan Payments -->
    <div class="card">
      <h3>Loan Payments History</h3>
      <table>
        <tr><th>Date</th><th>Amount</th><th>Method</th></tr>
        <?php if ($payments): ?>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['payment_date']) ?></td>
              <td>₱<?= number_format($p['amount'],2) ?></td>
              <td><?= htmlspecialchars($p['method']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3">No payments yet.</td></tr>
        <?php endif; ?>
      </table>
    </div>

    <!-- Proof Upload -->
    <div class="card upload-box">
      <h3>Upload Monthly Proof</h3>
      <?php if ($latest_proof): ?>
        <p>✅ Last uploaded: <?= htmlspecialchars($latest_proof['submitted_date']) ?></p>
        <p><?= htmlspecialchars($latest_proof['filename']) ?></p>
      <?php else: ?>
        <p>No proof uploaded</p>
      <?php endif; ?>
      <button onclick="location.href='upload_photo.php'">⬆ Upload Proof</button>
    </div>

    <!-- History -->
    <div class="card history">
      <h3>My History</h3>
      <ul>
        <?php if ($history): ?>
          <?php foreach ($history as $h): ?>
            <li><span><?= htmlspecialchars($h['action']) ?></span> <span><?= $h['created_at'] ?></span></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>No history records.</li>
        <?php endif; ?>
      </ul>
    </div>

  </main>
</body>
</html>
