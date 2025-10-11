<?php
require_once 'auth.php'; // Includes db.php, current_user(), require_role()
require_role(['staff']);
$user = current_user();
$staff_id = $user['id']; // ID of the currently logged-in staff

// Safe access for display
$user_full_name = $user['full_name'] ?? 'Staff User'; 
$user_email = $user['email'] ?? 'staff@system.com'; // Idinagdag para sa profile dropdown
$user_role = $user['role'] ?? 'staff';
$message = '';

// Check if PDO object is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('FATAL ERROR: Database connection object ($pdo) is not available. Please check db.php.');
}

// --- DATA FETCHING (for sidebar badge) ---
$unverified_payments_count = 0;
try {
    $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {}

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['action'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = $_POST['action'];

    $pdo->beginTransaction(); 
    try {
        $stmt_check = $pdo->prepare("
            SELECT 
                p.amount, 
                l.id AS loan_id,
                m.user_id, 
                u.full_name 
            FROM payments p 
            JOIN loans l ON p.loan_id = l.id
            JOIN members m ON l.member_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt_check->execute([$payment_id]);
        $payment_info = $stmt_check->fetch();

        if (!$payment_info) {
            $pdo->rollBack();
            $message = "<div class='message error'>Payment record not found or not linked to a valid loan/member.</div>";
        } else {
            $client_user_id = $payment_info['user_id'];
            $client_name = $payment_info['full_name'];
            $amount = number_format($payment_info['amount'], 2);

            $new_status = ($action === 'verify') ? 'verified' : 'rejected';
            $log_action = ($action === 'verify') ? 
                "Verified payment #$payment_id (₱$amount) for Loan {$payment_info['loan_id']} from $client_name." : 
                "Rejected payment #$payment_id (₱$amount) for Loan {$payment_info['loan_id']} from $client_name.";
            $notif_title = ($action === 'verify') ? "Payment Verified" : "Payment Rejected";
            $notif_msg = ($action === 'verify') ? 
                "Your payment of ₱$amount has been successfully **VERIFIED** by staff." : 
                "Your submitted payment of ₱$amount was **REJECTED**. Please check your proof or contact staff.";
            
            $message_extra = '';

            $stmt_update = $pdo->prepare("UPDATE payments 
                SET status = ?, collected_by = ?, verified_at = NOW() 
                WHERE id = ?");
            $stmt_update->execute([$new_status, $staff_id, $payment_id]);
            
            if ($action === 'verify') {
                $loan_id = $payment_info['loan_id'];
                
                $stmt_balance = $pdo->prepare("
                    SELECT 
                        l.amount AS loan_amount,
                        IFNULL(SUM(p.amount), 0) AS total_verified_paid
                    FROM loans l
                    LEFT JOIN payments p ON l.id = p.loan_id AND p.status = 'verified'
                    WHERE l.id = ?
                    GROUP BY l.id, l.amount
                ");
                $stmt_balance->execute([$loan_id]);
                $loan_data = $stmt_balance->fetch();

                if ($loan_data) {
                    $remaining_balance = $loan_data['loan_amount'] - $loan_data['total_verified_paid'];
                    if ($remaining_balance <= 0) {
                        $stmt_loan_update = $pdo->prepare("UPDATE loans SET status = 'paid' WHERE id = ? AND status != 'paid'");
                        $stmt_loan_update->execute([$loan_id]);
                        $message_extra = "**Loan ID $loan_id is now fully PAID!**";
                        if (function_exists('logAudit')) {
                            logAudit($pdo, $staff_id, "Loan ID: $loan_id status automatically updated to 'paid'.", "Loan ID: $loan_id");
                        }
                    } else {
                        // Ensure loan status is 'ongoing' if there's a balance and it's currently 'approved'
                        $stmt_loan_update_ongoing = $pdo->prepare("UPDATE loans SET status = 'ongoing' WHERE id = ? AND status = 'approved'");
                        $stmt_loan_update_ongoing->execute([$loan_id]);
                    }
                }
            }

            if (function_exists('logAudit')) {
                logAudit($pdo, $staff_id, $log_action, "Payment ID: $payment_id, Loan ID: {$payment_info['loan_id']}, New Status: $new_status");
            }

            if (function_exists('sendNotification')) {
                sendNotification($pdo, $client_user_id, $notif_title, $notif_msg);
            }
            
            if (!empty($message_extra)) {
                $message = "<div class='message success'>Payment #$payment_id successfully **$new_status**! $message_extra Client has been notified.</div>";
            } else {
                $message = "<div class='message success'>Payment #$payment_id successfully **$new_status**! Client has been notified.</div>";
            }

            $pdo->commit();
            // Re-fetch count after successful action
            $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div class='message error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- FETCH DATA FOR DISPLAY ---
try {
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.amount, 
            p.method, 
            p.payment_date,
            p.sender_account_number, 
            p.reference_number, 
            p.proof_of_payment_path,
            m.name AS member_name, 
            u.id AS client_user_id,
            l.id AS loan_id
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        JOIN members m ON l.member_id = m.id
        JOIN users u ON m.user_id = u.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at ASC
    ");
    $pending_payments = $stmt->fetchAll();
} catch (PDOException $e) {
    // If the columns are still missing after the attempted fix, this error will show up.
    // However, after running the SQL in Step 1, this should work.
    $pending_payments = [];
    $message .= "<div class='message error'>Error fetching data: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Client Payments</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }

        /* Sidebar Styles */
        .sidebar {
            width: 230px; background: #0f172a; color: #fff; min-height: 100vh;
            padding: 25px 20px; display: flex; flex-direction: column;
            position: fixed; 
        }
        .main { flex: 1; padding: 30px 40px; margin-left: 230px; }
        
        /* Sidebar Link Styles */
        .logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; }
        .logo-box img { height: 60px; width: auto; border-radius: 6px; }
        .sidebar a {
            color: #e2e8f0; text-decoration: none; padding: 12px 10px;
            margin-bottom: 8px; border-radius: 8px; display: block;
            font-weight: 500; position: relative;
        }
        .sidebar a:hover, .sidebar a.active { background: #1e293b; }
        .sidebar-link-badge .badge {
            position: absolute; top: 50%; right: 10px; transform: translateY(-50%);
            background: #ef4444; color: white; padding: 2px 7px;
            border-radius: 9999px; font-size: 12px; font-weight: 700;
        }
        .logout { margin-top: auto; background: #dc2626; color: #fff; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; }


        /* Header and Profile Dropdown Styles (ADDED/UPDATED) */
        header { 
            border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; 
            display: flex; justify-content: space-between; align-items: center; 
        }
        header h1 { font-size: 24px; color: #1e3a8a; }
        
        /* Profile Dropdown Container */
        .header-actions {
            display: flex; align-items: center; gap: 15px;
        }
        .profile-container {
            position: relative; cursor: pointer;
            background: #2563eb; color: white; padding: 8px 14px; 
            border-radius: 8px; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
        }
        .profile-container:hover { background: #1d4ed8; }

        .dropdown-menu {
            position: absolute; top: 100%; right: 0;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1500; min-width: 250px;
            padding: 5px 0; margin-top: 5px; display: none; /* Default hidden */
        }
        .dropdown-menu a {
            display: flex; align-items: center; padding: 10px 15px;
            text-decoration: none; color: #374151; font-size: 0.9rem;
        }
        .dropdown-menu a:hover { background: #f3f4f6; }
        .dropdown-menu a i { margin-right: 8px; width: 16px; }

        /* User Info Header in Dropdown */
        .dropdown-menu .user-details {
            padding: 10px 15px; 
            border-bottom: 1px solid #e2e8f0; 
            margin-bottom: 5px;
            color: #1e293b;
        }
        .dropdown-menu .user-details p {
            font-weight: 600; 
            margin: 0;
        }
        .dropdown-menu .user-details small {
            color: #64748b; 
            font-size: 12px;
            display: block;
        }
        
        /* Message Box Styles */
        .message { padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; border: 1px solid; }
        .message.success { background-color: #ecfdf5; color: #047857; border-color: #34d399; }
        .message.error { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }

        /* Table Styles */
        .payment-table { width: 100%; border-collapse: separate; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .payment-table th, .payment-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; }
        .payment-table th { background: #f1f5f9; font-weight: 600; font-size: 14px; }
        
        /* Fixed: Action Buttons Container */
        .action-btns {
            display: flex; /* Make buttons align horizontally */
            gap: 8px; /* Space between buttons */
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
            justify-content: flex-start; /* Align to the start in desktop view */
        }
        .action-btns button { 
            padding: 8px 12px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            white-space: nowrap; /* Prevent text wrapping inside buttons */
        }
        .verify-btn { background: #10b981; color: white; }
        .verify-btn:hover { background: #059669; }
        .reject-btn { background: #f43f5e; color: white; }
        .reject-btn:hover { background: #e11d48; }


        /* Responsive Table */
        @media (max-width: 992px) { 
             .payment-table thead { display: none; }
             .payment-table, .payment-table tr, .payment-table td, .payment-table th { display: block; }
             .payment-table tr { margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; }
             .payment-table td { 
                 text-align: right; 
                 padding-left: 50%; 
                 border-bottom: 1px dashed #f1f5f9;
                 position: relative; 
             }
             .payment-table td::before { 
                 content: attr(data-label); 
                 position: absolute; 
                 left: 10px; 
                 font-weight: 600; 
                 color: #475569; 
                 text-align: left;
             }
             .payment-table tr:last-child td:last-child { border-bottom: none; }
             .main { margin-left: 0; }
             .sidebar { width: 100%; min-height: unset; position: static; }

             /* Adjust action buttons for mobile */
             .payment-table td[data-label="Actions"] .action-btns {
                 justify-content: flex-end; /* Align buttons to the right on mobile */
             }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="staff_dashboard.php">🏠 Home</a>
        <a href="verify_payments.php" class="active sidebar-link-badge">
            💰 Verify Payments
            <?php if ($unverified_payments_count > 0): ?>
                <span class="badge"><?= $unverified_payments_count ?></span>
            <?php endif; ?>
        </a>
        <a href="record_payment.php">📝 Record Payments</a>
        <a href="members.php">👥 Manage Members</a>
        <a href="upload_member_photo.php">📸 View Proofs</a>
        
    </aside>

    <main class="main">
        <header>
            <h1>Review Pending Payments</h1>
            <div class="header-actions">
                <div class="profile-container" onclick="toggleProfileDropdown()">
                    <span><?= htmlspecialchars($user_full_name) ?></span> 
                    <i class="fas fa-user-circle fa-lg"></i>
                    
                    <div id="profileDropdown" class="dropdown-menu">
                         <div class="user-details">
                            <p><?= htmlspecialchars($user_full_name) ?></p>
                            <small><?= htmlspecialchars($user_email) ?></small>
                        </div>
                        <a href="change_password.php"> 
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <a href="index.php?logout=1" style="color:#dc2626;"> 
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <?= $message ?>

        <?php if (count($pending_payments) > 0): ?>
        <table class="payment-table">
            <thead>
                <tr>
                    <!-- <th>ID</th> Removed ID column header -->
                    <!-- <th>Loan ID</th> Removed Loan ID column header -->
                    <th>Member Name</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Sender Acct</th> 
                    <th>Reference #</th> 
                    <th>Proof</th>       
                    <th>Date Paid</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_payments as $payment): ?>
                <tr>
                    <!-- <td data-label="ID"><?= htmlspecialchars($payment['id']) ?></td> Removed ID data cell -->
                    <!-- <td data-label="Loan ID"><?= htmlspecialchars($payment['loan_id']) ?></td> Removed Loan ID data cell -->
                    <td data-label="Member Name"><?= htmlspecialchars($payment['member_name']) ?></td>
                    <td data-label="Amount">₱<?= number_format($payment['amount'], 2) ?></td>
                    <td data-label="Method"><?= htmlspecialchars(ucfirst($payment['method'])) ?></td>
                    
                    <td data-label="Sender Acct"><?= htmlspecialchars($payment['sender_account_number'] ?? 'N/A') ?></td>
                    <td data-label="Reference #"><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></td>
                    <td data-label="Proof">
                        <?php if (!empty($payment['proof_of_payment_path'])): ?>
                            <a href="<?= htmlspecialchars($payment['proof_of_payment_path']) ?>" target="_blank" style="color:#2563eb; text-decoration:none; font-weight:600;">
                                View Proof <i class="fas fa-external-link-alt" style="font-size: 0.7em;"></i>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td data-label="Date Paid"><?= htmlspecialchars($payment['payment_date']) ?></td>
                    <td data-label="Actions">
                        <div class="action-btns">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <button type="submit" name="action" value="verify" class="verify-btn">✅ Verify</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <button type="submit" name="action" value="reject" class="reject-btn">❌ Reject</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="message success">
            🎉 There are no pending payments for review at this time.
        </div>
        <?php endif; ?>
    </main>

    <script>
    // --- PROFILE DROPDOWN LOGIC (FOR STAFF) ---
    function toggleProfileDropdown() {
        const dd = document.getElementById('profileDropdown');
        if (dd) {
             dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
        }
    }

    // Global Click Listener (Para sa pag-sara ng dropdown)
    window.onclick = function(e) {
        // Close Profile Dropdown ONLY if the click is outside the container
        const profileContainer = e.target.closest('.profile-container');
        if (!profileContainer) {
            const profileDd = document.getElementById('profileDropdown');
            if (profileDd) profileDd.style.display = 'none';
        }
    }
    </script>
</body>
</html>