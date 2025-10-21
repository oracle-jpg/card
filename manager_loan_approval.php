<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch manager info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Role check
if ($user['role'] !== 'manager') {
    header("Location: index.php?access_denied=1");
    exit;
}

// Handle loan approval/rejection
if (isset($_POST['action'], $_POST['loan_id'])) {
    $loan_id = $_POST['loan_id'];
    $action = $_POST['action'];

    $loan_info = $pdo->prepare("
        SELECT l.member_id, l.amount, l.status, m.user_id AS client_user_id
        FROM loans l
        JOIN members m ON l.member_id = m.id
        WHERE l.id = ?
    ");
    $loan_info->execute([$loan_id]);
    $info = $loan_info->fetch();

    if (!$info) {
        header("Location: manager_loan_approval.php?error=Loan not found!");
        exit;
    }

    $member_id = $info['member_id'];
    $loan_amount = $info['amount'];
    $client_user_id = $info['client_user_id'];
    $current_status = $info['status'];

    $pdo->beginTransaction();
    try {
        if ($action === 'approve' && $current_status === 'pending') {
            // Update status to approved and mark as released
            $pdo->prepare("
                UPDATE loans 
                SET status='ongoing', disbursed_date=NOW() 
                WHERE id=?
            ")->execute([$loan_id]);

            // Update member's active loan balance
            $pdo->prepare("UPDATE members SET loan_amount = loan_amount + ? WHERE id=?")
                ->execute([$loan_amount, $member_id]);

            sendNotification($pdo, $client_user_id, "Loan Released ✅", 
                "Your ₱" . number_format($loan_amount, 2) . " loan has been released and is now active.");

            logAudit($pdo, $user['id'], "Released Loan ID {$loan_id} to Member {$member_id}");

        } elseif ($action === 'reject' && $current_status === 'pending') {
            $pdo->prepare("UPDATE loans SET status='rejected' WHERE id=?")->execute([$loan_id]);

            sendNotification($pdo, $client_user_id, "Loan Rejected ❌", 
                "Your loan request of ₱" . number_format($loan_amount, 2) . " was rejected by the manager.");

            logAudit($pdo, $user['id'], "Rejected Loan ID {$loan_id} for Member {$member_id}");
        }

        $pdo->commit();
        header("Location: manager_loan_approval.php?msg=success");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: manager_loan_approval.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}

// Messages
$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = "<div class='msg success'>✅ Loan action completed successfully.</div>";
} elseif (isset($_GET['error'])) {
    $message = "<div class='msg error'>⚠️ " . htmlspecialchars($_GET['error']) . "</div>";
}

// Fetch pending & all loans
$pendingLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.purpose, l.status, l.created_at
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.status='pending'
    ORDER BY l.created_at DESC
")->fetchAll();

$allLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.purpose, l.status, l.disbursed_date, l.created_at
    FROM loans l
    JOIN members m ON l.member_id = m.id
    ORDER BY l.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manager - Loan Approvals</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{display:flex;background:#f1f5f9;color:#1e293b;}
.sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
.logo-box{display:flex;justify-content:left;align-items:center;padding:15px 0;margin-bottom:30px;}
.logo-box img{height:60px;border-radius:6px;}
.sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
.sidebar a:hover,.sidebar .active{background:#1e293b;color:#fff;}
.main{flex:1;padding:30px;}
h1{color:#1e3a8a;margin-bottom:15px;}
.msg{padding:12px;border-radius:6px;margin-bottom:20px;font-weight:600;}
.msg.success{background:#dcfce7;color:#166534;}
.msg.error{background:#fee2e2;color:#991b1b;}
table{width:100%;border-collapse:collapse;background:white;box-shadow:0 2px 6px rgba(0,0,0,0.1);border-radius:8px;overflow:hidden;}
th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:0.9rem;}
th{background:#f8fafc;color:#1e293b;}
.status{padding:4px 8px;border-radius:5px;font-weight:600;font-size:13px;text-transform:capitalize;}
.pending{background:#fef9c3;color:#854d0e;}
.ongoing{background:#dcfce7;color:#166534;}
.rejected{background:#fee2e2;color:#991b1b;}
form{display:inline;}
button{background:#2563eb;color:white;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;margin-right:5px;}
button.reject{background:#dc2626;}
button:hover{opacity:0.9;}
.back-btn{display:inline-block;margin-top:20px;background:#475569;color:white;text-decoration:none;padding:10px 14px;border-radius:6px;}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="logo-box">
    <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Logo">
  </div>
  <a href="manager_dashboard.php">🏠 Home</a>
  <a href="manager_loan_approval.php" class="active">✅ Loan Approvals</a>
  <a href="loan_overview.php">💼 Loan Overview</a>
  <a href="staff_performance.php">👥 Staff Performance</a>
  <a href="generate_reports.php">📊 Reports</a>
</aside>

<!-- Main -->
<main class="main">
  <h1>📋 Loan Approval Management</h1>
  <?= $message ?>

  <h2>Pending Requests (<?= count($pendingLoans) ?>)</h2>
  <?php if ($pendingLoans): ?>
  <table>
    <tr>
      <th>Member</th><th>Amount</th><th>Purpose</th><th>Requested</th><th>Action</th>
    </tr>
    <?php foreach ($pendingLoans as $loan): ?>
    <tr>
      <td><?= htmlspecialchars($loan['member_name']) ?></td>
      <td>₱<?= number_format($loan['amount'],2) ?></td>
      <td><?= htmlspecialchars($loan['purpose']) ?></td>
      <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
      <td>
        <form method="POST"><input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
          <button type="submit" name="action" value="approve">Approve</button>
          <button type="submit" name="action" value="reject" class="reject">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p style="background:#e0f2fe;border:1px solid #93c5fd;padding:10px;border-radius:6px;">No pending loan requests.</p>
  <?php endif; ?>

  <h2 style="margin-top:40px;">All Loans (<?= count($allLoans) ?>)</h2>
  <table>
    <tr>
      <th>Member</th><th>Amount</th><th>Purpose</th><th>Status</th><th>Released</th>
    </tr>
    <?php foreach ($allLoans as $loan): ?>
    <tr>
      <td><?= htmlspecialchars($loan['member_name']) ?></td>
      <td>₱<?= number_format($loan['amount'],2) ?></td>
      <td><?= htmlspecialchars($loan['purpose']) ?></td>
      <td><span class="status <?= $loan['status'] ?>"><?= $loan['status'] ?></span></td>
      <td><?= $loan['disbursed_date'] ? date('M d, Y', strtotime($loan['disbursed_date'])) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <a href="manager_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
</main>

</body>
</html>