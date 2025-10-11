<?php
// Tiyakin na ang <?php tag ay nasa UNANG-UNANG LINE, walang space o newline sa taas.
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch manager info and ensure role is 'manager'
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    // Use header redirect instead of alert for clean redirection
    header("Location: index.php?access_denied=1"); 
    exit;
}

// Handle approve/reject actions
if (isset($_POST['action']) && isset($_POST['loan_id'])) {
    $loan_id = $_POST['loan_id'];
    $action = $_POST['action'];

    // 1. Get loan and member details before updating
    $loan_info = $pdo->prepare("
        SELECT l.member_id, l.amount, m.user_id AS client_user_id
        FROM loans l
        JOIN members m ON l.member_id = m.id
        WHERE l.id = ?
    ");
    $loan_info->execute([$loan_id]);
    $info = $loan_info->fetch();

    if (!$info) {
        header("Location: manager_loan_approval.php?error=" . urlencode('Loan not found!'));
        exit;
    }
    
    $member_id_to_update = $info['member_id'];
    $loan_amount = $info['amount'];
    $client_user_id = $info['client_user_id'];

    // TRANSACTION block to ensure data integrity
    $pdo->beginTransaction();

    try {
        if ($action === 'approve') {
            // 1. Update loan status and set start date
            $pdo->prepare("UPDATE loans SET status='approved', loan_start=CURDATE() WHERE id=?")->execute([$loan_id]);

            // 2. Update the member's total outstanding loan amount
            $pdo->prepare("UPDATE members SET loan_amount = loan_amount + ? WHERE id=?")
                ->execute([$loan_amount, $member_id_to_update]);

            // 3. Send notification and log audit
            sendNotification($pdo, $client_user_id, "Loan Approved ✅", 
                            "Your loan request of ₱" . number_format($loan_amount, 2) . " has been approved and disbursed.");
            logAudit($pdo, $user['id'], "Approved Loan ID: " . $loan_id . " for member " . $member_id_to_update);


        } elseif ($action === 'reject') {
            // 1. Update loan status
            $pdo->prepare("UPDATE loans SET status='rejected' WHERE id=?")->execute([$loan_id]);

            // 2. Send notification and log audit
            sendNotification($pdo, $client_user_id, "Loan Rejected ❌", 
                            "Unfortunately, your loan request of ₱" . number_format($loan_amount, 2) . " was rejected by the manager.");
            logAudit($pdo, $user['id'], "Rejected Loan Request ID: " . $loan_id . " for member " . $member_id_to_update);
        }
        
        $pdo->commit();
        header("Location: manager_loan_approval.php?msg=success");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Better error message for debugging
        header("Location: manager_loan_approval.php?error=" . urlencode("Transaction error: " . $e->getMessage()));
        exit;
    }
}

// --- Display Messages ---
$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = "<p style='color:green; font-weight:bold; padding:10px; border:1px solid green; background:#dcfce7; border-radius:5px; margin-bottom:15px;'>Loan action successful!</p>";
} elseif (isset($_GET['error'])) {
    $message = "<p style='color:red; font-weight:bold; padding:10px; border:1px solid red; background:#fee2e2; border-radius:5px; margin-bottom:15px;'>Error: " . htmlspecialchars($_GET['error']) . "</p>";
}


// Fetch pending loans (UPDATED: Added l.purpose)
$pendingLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.status, l.created_at, l.purpose
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.status='pending'
    ORDER BY l.created_at DESC
")->fetchAll();

// Fetch all loans (UPDATED: Added l.purpose)
$allLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.status, l.loan_start, l.created_at, l.purpose
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
<title>Manager Loan Approval</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#f8fafc; color:#1e293b; padding:30px; }
h1 { color:#1e3a8a; border-bottom: 2px solid #e0e7ff; padding-bottom: 10px; }
table { width:100%; border-collapse:collapse; margin-top:20px; background:white; border-radius:8px; overflow:hidden; box-shadow:0 3px 10px rgba(0,0,0,0.1); }
th, td { padding:12px; border-bottom:1px solid #e2e8f0; text-align:left; }
th { background:#f1f5f9; }
.status { padding:4px 8px; border-radius:5px; font-weight:600; font-size:13px; text-transform:capitalize; }
.pending { background:#fef9c3; color:#854d0e; }
.approved { background:#dcfce7; color:#166534; }
.rejected { background:#fee2e2; color:#991b1b; }
.paid { background:#bfdbfe; color:#1e3a8a; } 
form { display:inline; margin-right: 5px; }
button {
    background:#2563eb; color:white; border:none; padding:8px 14px;
    border-radius:6px; cursor:pointer; font-size:13px; font-weight:500;
    transition: background 0.2s;
}
button.reject { background:#dc2626; }
button:hover { opacity:0.9; }
.btn-secondary {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 15px;
    background: #475569;
    color: white;
    text-decoration: none;
    border-radius: 6px;
}
</style>
</head>
<body>

<h1>📋 Loan Requests Approval</h1>

<?= $message ?>

<h2>Pending Requests (<?= count($pendingLoans) ?>)</h2>
<?php if ($pendingLoans): ?>
<table>
    <tr>
        <th>ID</th>
        <th>Member</th>
        <th>Amount</th>
        <th>Purpose</th>
        <th>Requested Date</th>
        <th>Action</th>
    </tr>
    <?php foreach ($pendingLoans as $loan): ?>
    <tr>
        <td><?= $loan['id'] ?></td>
        <td><?= htmlspecialchars($loan['member_name']) ?></td>
        <td>₱<?= number_format($loan['amount'],2) ?></td>
        <td><?= htmlspecialchars($loan['purpose']) ?></td>
        <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
        <td>
            <form method="post">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <button type="submit" name="action" value="approve" title="Approve this loan request">Approve</button>
            </form>
            <form method="post">
                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                <button type="submit" name="action" value="reject" class="reject" title="Reject this loan request">Reject</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p style="padding: 10px; background: #e0f2fe; border: 1px solid #90cdf4; border-radius: 6px;">No pending loan requests at this time.</p>
<?php endif; ?>

<h2 style="margin-top:40px;">All Loan Records (<?= count($allLoans) ?>)</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Member</th>
        <th>Amount</th>
        <th>Purpose</th>
        <th>Status</th>
        <th>Loan Start Date</th>
    </tr>
    <?php foreach ($allLoans as $loan): ?>
    <tr>
        <td><?= $loan['id'] ?></td>
        <td><?= htmlspecialchars($loan['member_name']) ?></td>
        <td>₱<?= number_format($loan['amount'],2) ?></td>
        <td><?= htmlspecialchars($loan['purpose']) ?></td>
        <td><span class="status <?= $loan['status'] ?>"><?= $loan['status'] ?></span></td>
        <td><?= $loan['loan_start'] ? date('M d, Y', strtotime($loan['loan_start'])) : 'Pending/Rejected' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<p><a href="manager_dashboard.php" class="btn-secondary">⬅ Back to Home</a></p>

</body>
</html>
