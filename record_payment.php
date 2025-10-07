<?php
require_once 'auth.php';
require_role(['staff']);
require_once 'db.php';

$user = current_user();
$msg = "";

// Fetch all active members with ongoing loans
$stmt = $pdo->query("
    SELECT l.id AS loan_id, m.name AS member_name, l.amount, l.status 
    FROM loans l 
    JOIN members m ON l.member_id = m.id 
    WHERE l.status = 'ongoing'
    ORDER BY m.name ASC
");
$loans = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = $_POST['loan_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'cash';
    $payment_date = date('Y-m-d');
    $collected_by = $user['id'];

    if ($loan_id && $amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO payments (loan_id, amount, payment_date, collected_by, method)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$loan_id, $amount, $payment_date, $collected_by, $method]);

        // Audit log
        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        $log->execute([$user['id'], 'Record Payment', "Recorded ₱$amount via $method for Loan ID #$loan_id"]);

        $msg = "✅ Payment recorded successfully!";
    } else {
        $msg = "⚠ Please select a valid loan and amount.";
    }
}
// 🔔 Notify roles
notifyRole($pdo, 'admin', 'Payment Recorded', "{$user['full_name']} recorded a new payment.");
notifyRole($pdo, 'manager', 'Payment Update', "{$user['full_name']} recorded a client payment.");
if (isset($member_user_id)) {
    sendNotification($pdo, $member_user_id, 'Payment Confirmed', 'Your payment has been successfully recorded.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Record Payment - Staff</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif;}
    body {display: flex; background: #f8fafc; color: #1e293b;}

    .sidebar {
      width: 230px; background: #0f172a; color: #fff; min-height: 100vh;
      padding: 25px 20px; display: flex; flex-direction: column;
    }
    .sidebar h2 {font-size: 20px; margin-bottom: 30px;}
    .sidebar a {
      color: #e2e8f0; text-decoration: none; padding: 10px; margin-bottom: 8px;
      border-radius: 6px; display: block; transition: 0.3s;
    }
    .sidebar a:hover {background: #1e293b; color: #fff;}
    .logout {
      margin-top: auto; background: #dc2626; color: #fff; text-align: center;
      padding: 10px; border-radius: 6px; text-decoration: none; transition: 0.3s;
    }
    .logout:hover {background: #b91c1c;}

    .main {flex: 1; padding: 30px 40px;}
    header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;}
    header h1 {font-size: 24px; font-weight: 600; color: #1e3a8a;}
    .profile {background: #e0f2fe; color: #1e3a8a; padding: 8px 15px; border-radius: 8px; font-weight: 500;}

    form {
      background: #fff; padding: 25px; border-radius: 10px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.08); max-width: 600px;
    }
    label {display: block; font-weight: 500; margin-bottom: 6px; margin-top: 15px;}
    select, input {
      width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;
      font-size: 15px;
    }
    button {
      background: #2563eb; border: none; color: white; padding: 12px 20px;
      border-radius: 6px; cursor: pointer; font-size: 15px;
      margin-top: 20px; transition: background 0.3s;
    }
    button:hover {background: #1d4ed8;}
    .msg {margin-top: 15px; padding: 10px; font-weight: 500;}
    .success {background: #dcfce7; color: #166534; border-radius: 6px;}
    .warning {background: #fef3c7; color: #92400e; border-radius: 6px;}
  </style>
</head>
<body>

  <aside class="sidebar">
    <h2>Staff Panel</h2>
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="record_payment.php">💰 Record Payment</a>
    <a href="members.php">👥 Manage Members</a>
    <a href="upload_member_photo.php">📸 Upload Proof</a>
    <a href="notifications.php">🔔 Notifications <span id="notifCount" style="background:#ef4444;color:white;padding:2px 6px;border-radius:10px;font-size:12px;margin-left:6px;">0</span></a>
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
  </aside>

  <main class="main">
    <header>
      <h1>💰 Record Client Payment</h1>
      <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (Staff)</div>
    </header>

    <form method="POST">
      <label>Client Loan</label>
      <select name="loan_id" required>
        <option value="">-- Select Client Loan --</option>
        <?php foreach ($loans as $loan): ?>
          <option value="<?= $loan['loan_id'] ?>">
            <?= htmlspecialchars($loan['member_name']) ?> — ₱<?= number_format($loan['amount'], 2) ?> (<?= $loan['status'] ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <label>Amount (₱)</label>
      <input type="number" name="amount" step="0.01" placeholder="Enter payment amount" required>

      <label>Payment Method</label>
      <select name="method" required>
        <option value="cash">Cash</option>
        <option value="gcash">GCash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="other">Other</option>
      </select>

      <button type="submit">Record Payment</button>

      <?php if ($msg): ?>
        <p class="msg <?= str_contains($msg, '✅') ? 'success' : 'warning' ?>"><?= $msg ?></p>
      <?php endif; ?>
    </form>
  </main>

</body>
</html>
