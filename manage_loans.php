<?php
session_start();
require 'db.php';
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check role (admin only)
if ($user['role'] !== 'admin') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}

$msg = '';

// --- 1. Handle Loan Approval/Rejection or Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_id'], $_POST['action'])) {
    $loan_id = $_POST['loan_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE loans SET status='approved', disbursed_date=CURDATE() WHERE id=? AND status='pending'")
                ->execute([$loan_id]);
            
            // Fetch client details for notification
            $client_info = $pdo->query("SELECT u.id AS user_id FROM loans l JOIN members m ON l.member_id = m.id JOIN users u ON m.user_id = u.id WHERE l.id = $loan_id")->fetch();

            if ($client_info) {
                sendNotification($pdo, $client_info['user_id'], "Loan Approved", "Your loan application (ID: $loan_id) has been approved and is ready for disbursement.");
            }
            
            $msg = "Loan ID $loan_id approved and marked as Disbursed. Client notified.";
            $action_log = "Approved Loan (ID: $loan_id)";

        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE loans SET status='rejected' WHERE id=? AND status='pending'")
                ->execute([$loan_id]);

            // Fetch client details for notification
            $client_info = $pdo->query("SELECT u.id AS user_id FROM loans l JOIN members m ON l.member_id = m.id JOIN users u ON m.user_id = u.id WHERE l.id = $loan_id")->fetch();

            if ($client_info) {
                sendNotification($pdo, $client_info['user_id'], "Loan Rejected", "Your loan application (ID: $loan_id) has been rejected. Please contact us for details.");
            }
            
            $msg = "Loan ID $loan_id rejected. Client notified.";
            $action_log = "Rejected Loan (ID: $loan_id)";

        } elseif (in_array($action, ['ongoing', 'closed', 'defaulted'])) {
            // For updating status of approved/ongoing loans
            $pdo->prepare("UPDATE loans SET status=? WHERE id=? AND status!='pending' AND status!='rejected'")
                ->execute([$action, $loan_id]);
            $msg = "Loan ID $loan_id status updated to " . ucfirst($action) . ".";
            $action_log = "Updated Loan Status (ID: $loan_id) to $action";
        }

        if (isset($action_log)) {
            $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)")
                ->execute([$user_id, $action_log]);
        }

    } catch (PDOException $e) {
        $msg = "Database Error: " . $e->getMessage();
    }
}


// --- 2. Fetch All Loans with Member Names ---
$loans = $pdo->query("
    SELECT 
        l.*, 
        m.name AS member_name
    FROM loans l
    JOIN members m ON l.member_id = m.id
    ORDER BY FIELD(l.status, 'pending', 'ongoing', 'approved', 'closed', 'rejected', 'defaulted'), l.created_at DESC
")->fetchAll();

// Fetch notifications for header (copied from dashboard structure)
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->execute([$user['id']]);
$notifications = $notif_stmt->fetchAll();
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
$count_stmt->execute([$user['id']]);
$unread_count = $count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Loans - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles (Copied from admin_dashboard.php for consistency) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }
        .sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; position: fixed; }
        .logo-box {
    /* Tinanggal ang h2 styles */
    display: flex;
    justify-content: left; /* I-center ang image */
    align-items: center;
    padding: 15px 0;
    margin-bottom: 30px;
    border-radius: 8px;
}
.logo-box img {
    height: 60px; /* Fixed height for the logo */
    width: auto;
    border-radius: 6px; 
}
        .sidebar a { display: block; color: #e2e8f0; padding: 10px; margin: 8px 0; border-radius: 6px; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover { background: #1e293b; color: #fff; }
        .main { flex: 1; margin-left: 230px; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        header h1 { font-size: 22px; font-weight: 600; color: #1e3a8a; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .profile { background: #2563eb; color: #fff; padding: 8px 14px; border-radius: 6px; font-weight: 500; }
        .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .card h3 { margin-bottom: 15px; font-size: 18px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f1f5f9; }
        
        /* Status Colors for Loans */
        .status { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 14px; }
        .status.pending { background: #fefce8; color: #ca8a04; }
        .status.approved, .status.ongoing { background: #dcfce7; color: #16a34a; }
        .status.rejected, .status.defaulted { background: #fee2e2; color: #dc2626; }
        .status.closed { background: #e0f2fe; color: #0284c7; }

        .action-form { display: flex; gap: 5px; align-items: center; }
        .action-form button, .action-form select { padding: 6px 10px; border-radius: 6px; border: none; font-size: 14px; cursor: pointer; font-weight: 500;}
        .action-form .approve { background: #22c55e; color: white; }
        .action-form .reject { background: #ef4444; color: white; }
        .action-form .update { background: #3b82f6; color: white; }
        .action-form .approve:hover { background: #16a34a; }
        .action-form .reject:hover { background: #dc2626; }
        .action-form .update:hover { background: #2563eb; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; font-weight: 600; text-align: center; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
        .error { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
          <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="admin_dashboard.php">🏠 Home</a>
        <a href="manage_members.php">👥 Manage Members</a>
        <a href="manage_loans.php">💼 Manage Loans</a>
        <a href="record_payment.php">💰 Record Payments</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="create_user.php">➕ Create Staff / Manager</a>
        <a href="index.php?logout=1">🚪 Logout</a>
    </aside>

    <!-- Main -->
    <main class="main">
        <header>
            <h1>💼 Manage All Loans</h1>
            <div class="header-right">
                <!-- Placeholder for notification bell inclusion -->
                <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
            </div>
        </header>

        <?php if (!empty($msg)): ?>
            <div class="message <?= strpos($msg, 'Error') !== false ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>All Loans (<?= count($loans) ?> Records)</h3>
            
            <table>
                <tr>
                    <th>ID</th>
                    <th>Client Name</th>
                    <th>Amount</th>
                    <th>Term</th>
                    <th>Interest</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
                <?php if ($loans): ?>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><?= $loan['id'] ?></td>
                            <td><?= htmlspecialchars($loan['member_name']) ?></td>
                            <td>₱<?= number_format($loan['amount'], 2) ?></td>
                            <td><?= $loan['term_months'] ?> mo.</td>
                            <td><?= $loan['interest_rate'] ?>%</td>
                            <td>
                                <span class="status <?= $loan['status'] ?>"><?= ucfirst($loan['status']) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                            <td>
                                <form method="post" class="action-form">
                                    <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">

                                    <?php if ($loan['status'] === 'pending'): ?>
                                        <button type="submit" name="action" value="approve" class="approve">Approve</button>
                                        <button type="submit" name="action" value="reject" class="reject">Reject</button>
                                    <?php elseif ($loan['status'] === 'approved' || $loan['status'] === 'ongoing'): ?>
                                        <select name="action" required style="border:1px solid #ccc;">
                                            <option value="">Change Status</option>
                                            <option value="ongoing">Set Ongoing</option>
                                            <option value="closed">Mark Closed</option>
                                            <option value="defaulted">Mark Defaulted</option>
                                        </select>
                                        <button type="submit" class="update">Update</button>
                                    <?php else: ?>
                                        <span style="color:#64748b; font-style:italic;">No actions available.</span>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8">No loan records found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</body>
</html>
