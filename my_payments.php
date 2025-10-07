<?php
require_once 'auth.php';
require_once 'db.php';

$user = current_user();

// Allow both staff and client
if (!in_array($user['role'], ['client', 'staff'])) {
    header("Location: index.php");
    exit;
}

// ✅ For clients — get linked member record
$member = null;
if ($user['role'] === 'client') {
    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $member = $stmt->fetch();
    if (!$member) {
        die("❌ Error: Your account is not linked to any member record. Please contact support.");
    }
}

// ✅ For staff — optional: select all payments
if ($user['role'] === 'staff') {
    $stmt = $pdo->query("
        SELECT p.*, m.name AS member_name 
        FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        JOIN members m ON l.member_id = m.id 
        ORDER BY p.payment_date DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, l.amount AS loan_amount 
        FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        WHERE l.member_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$member['id']]);
}
$payments = $stmt->fetchAll();

// ✅ Compute outstanding balance (total loan - total payments)
$outstanding = 0;
if ($user['role'] === 'client') {
    $loanStmt = $pdo->prepare("SELECT SUM(amount) FROM loans WHERE member_id = ?");
    $loanStmt->execute([$member['id']]);
    $totalLoan = floatval($loanStmt->fetchColumn() ?? 0);

    $payStmt = $pdo->prepare("
        SELECT SUM(p.amount) FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        WHERE l.member_id = ?
    ");
    $payStmt->execute([$member['id']]);
    $totalPaid = floatval($payStmt->fetchColumn() ?? 0);

    $outstanding = $totalLoan - $totalPaid;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{display:flex;background:#f8fafc;color:#1e293b;}
.sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
.sidebar h2{font-size:20px;margin-bottom:30px;}
.sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
.sidebar a:hover{background:#1e293b;color:#fff;}
.logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
.logout:hover{background:#b91c1c;}
.main{flex:1;padding:30px 40px;}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
header h1{font-size:24px;font-weight:600;color:#1e3a8a;}
.profile{background:#e0f2fe;color:#1e3a8a;padding:8px 15px;border-radius:8px;font-weight:500;}
.card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:25px;}
.card h2{font-size:18px;margin-bottom:15px;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;}
button{background:#2563eb;border:none;color:white;padding:8px 14px;border-radius:6px;cursor:pointer;}
button:hover{background:#1d4ed8;}
.no-data{text-align:center;padding:20px;color:#64748b;}
.status-paid{color:#16a34a;font-weight:600;}
.balance{font-weight:600;color:#1e3a8a;}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <h2><?= ucfirst($user['role']) ?> Panel</h2>
  <?php if($user['role']=='client'): ?>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php">💼 My Loans</a>
    <a href="payments.php">💰 My Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php">📜 My History</a>
  <?php else: ?>
    <a href="staff_dashboard.php">🏠 Dashboard</a>
    <a href="members.php">👥 Members</a>
    <a href="record_payment.php">💰 Record Payment</a>
  <?php endif; ?>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<!-- Main -->
<main class="main">
  <header>
    <h1>💰 Payments</h1>
    <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</div>
  </header>

  <div class="card">
    <h2>📋 Payment History</h2>
    <table>
      <tr><th>Date</th><th>Amount (₱)</th><th>Method</th><?php if($user['role']=='staff') echo "<th>Member</th>"; ?></tr>
      <?php if ($payments): ?>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['payment_date']) ?></td>
            <td><?= number_format($p['amount'], 2) ?></td>
            <td><?= htmlspecialchars($p['method']) ?></td>
            <?php if($user['role']=='staff'): ?>
              <td><?= htmlspecialchars($p['member_name']) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="<?= $user['role']=='staff' ? 4 : 3 ?>" class="no-data">No payments yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <?php if($user['role']=='client'): ?>
  <div class="card">
    <h2>💵 Outstanding Balance</h2>
    <p>Your current balance: <span class="balance">₱<?= number_format(max($outstanding,0), 2) ?></span></p>
    <button onclick="alert('Payment feature coming soon!')">Make a Payment</button>
    <button onclick="window.location='export_payment_pdf.php'">Download Receipt (PDF)</button>
  </div>
  <?php endif; ?>
</main>

</body>
</html>
