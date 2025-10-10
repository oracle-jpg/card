<?php
session_start();
require 'db.php';
require_once 'db.php'; // ensure notification functions are available

// --- Utility Functions for Financial Calculation (Moved to the top) ---

/**
 * Calculates the total amount paid for a given loan.
 */
function calculateTotalPaid($pdo, $loan_id) {
    $payments_stmt = $pdo->prepare("SELECT SUM(amount) AS total_paid FROM payments WHERE loan_id = ?");
    $payments_stmt->execute([$loan_id]);
    return (float) $payments_stmt->fetchColumn() ?: 0.00;
}

/**
 * Calculates the total payable amount (Principal + Simple Interest).
 */
function calculateTotalPayable($principal, $rate, $term_months) {
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return round($principal + $interest_amount, 2);
}

/**
 * Full calculation, including fetching recent payments.
 */
function calculateLoanFinancials($pdo, $loan_details) {
    $loan_id = $loan_details['id'];
    $loan_details['total_paid'] = calculateTotalPaid($pdo, $loan_id);
    
    $total_payable = calculateTotalPayable(
        $loan_details['amount'], 
        $loan_details['interest_rate'], 
        $loan_details['term_months']
    );
    
    $loan_details['total_payable'] = $total_payable;
    $loan_details['remaining_balance'] = max(0, $loan_details['total_payable'] - $loan_details['total_paid']);
    
    // Fetch recent payments for display
    $recent_payments_stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC LIMIT 5");
    $recent_payments_stmt->execute([$loan_id]);
    $loan_details['recent_payments'] = $recent_payments_stmt->fetchAll();
    
    return $loan_details;
}

// --- 1. Authentication and Authorization Check ---
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check role (admin/manager/staff can record payments)
if ($user['role'] !== 'admin' && $user['role'] !== 'manager' && $user['role'] !== 'staff') {
    echo "<script>alert('Access denied! Only staff/managers/admins can record payments.'); window.location='index.php';</script>";
    exit;
}

$loan_details = null;
$found_loans = []; // Array to hold multiple results if searching by name
$msg = '';

// --- 2. Handle Payment Recording ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $loan_id = $_POST['loan_id'];
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $payment_date = $_POST['payment_date'];
    $method = $_POST['method'];

    if ($amount === false || $amount <= 0) {
        $msg = "Error: Invalid payment amount. Please enter a positive number.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // 1. Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (loan_id, payment_date, amount, method, collected_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$loan_id, $payment_date, $amount, $method, $user_id]);

            // 2. Update loan status to 'ongoing' if it was 'approved'
            $pdo->prepare("UPDATE loans SET status = 'ongoing' WHERE id = ? AND status = 'approved'")
                ->execute([$loan_id]);

            // 3. Log action
            $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)")
                ->execute([$user_id, "Recorded payment of ₱$amount for Loan ID: $loan_id"]);
            
            $pdo->commit(); // Commit transaction
            
            $msg = "Payment of ₱" . number_format($amount, 2) . " successfully recorded for Loan ID: $loan_id.";
            
            // Send notification to the client
            $client_info = $pdo->query("SELECT u.id AS user_id FROM loans l JOIN members m ON l.member_id = m.id JOIN users u ON m.user_id = u.id WHERE l.id = $loan_id")->fetch();

            if ($client_info) {
                // Assuming sendNotification function is defined in db.php or required file
                sendNotification($pdo, $client_info['user_id'], "Payment Recorded", "₱" . number_format($amount, 2) . " payment for Loan ID $loan_id has been successfully recorded on $payment_date.");
            }

            // Clear loan details after successful recording
            $loan_details = null; 
            
        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback if error occurs
            $msg = "Database Error recording payment: " . $e->getMessage();
        }
    }
}


// --- 3. Handle Loan Search / Selection ---

// Handle step 2: User selects a loan from the list of multiple results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_loan_id'])) {
    $search_id = trim($_POST['select_loan_id']);
    
    $stmt = $pdo->prepare("
        SELECT l.*, m.name AS member_name, m.address, m.phone
        FROM loans l JOIN members m ON l.member_id = m.id
        WHERE l.id = ? AND l.status IN ('approved', 'ongoing', 'defaulted')
    ");
    $stmt->execute([$search_id]);
    $raw_loan = $stmt->fetch();

    if ($raw_loan) {
        $loan_details = calculateLoanFinancials($pdo, $raw_loan);
        // Removed specific "Loan ID selected" message to keep it cleaner
    } else {
        $msg = "Error: Selected Loan ID not found.";
    }
}


// Handle step 1: Initial Search by ID or Name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_term'])) {
    $search_term = trim($_POST['search_term']);
    
    if (is_numeric($search_term) && $search_term > 0) {
        // Search by Loan ID (strict match)
        $stmt = $pdo->prepare("
            SELECT l.*, m.name AS member_name, m.address, m.phone
            FROM loans l JOIN members m ON l.member_id = m.id
            WHERE l.id = ? AND l.status IN ('approved', 'ongoing', 'defaulted')
        ");
        $stmt->execute([$search_term]);
        $raw_loan = $stmt->fetch();
        
        if ($raw_loan) {
            $loan_details = calculateLoanFinancials($pdo, $raw_loan);
        } else {
            $msg = "Error: Loan ID $search_term not found or not in 'Approved', 'Ongoing', or 'Defaulted' status.";
        }
        
    } else {
        // Search by Client Name (partial match)
        $search_name_like = "%" . $search_term . "%";
        $stmt = $pdo->prepare("
            SELECT l.*, m.name AS member_name, m.address, m.phone
            FROM loans l JOIN members m ON l.member_id = m.id
            WHERE m.name LIKE ? AND l.status IN ('approved', 'ongoing', 'defaulted')
            ORDER BY m.name ASC, l.id DESC
        ");
        $stmt->execute([$search_name_like]);
        $raw_loans = $stmt->fetchAll();
        
        if (empty($raw_loans)) {
            $msg = "Error: No active loan found matching client name '<strong>" . htmlspecialchars($search_term) . "</strong>'.";
        } elseif (count($raw_loans) === 1) {
            // Found exactly one loan
            $loan_details = calculateLoanFinancials($pdo, $raw_loans[0]);
            
        } else {
            // Multiple loans found, prepare them for selection list
            // --- INALIS ANG MESSAGE DITO PARA HINDI NA DOBLE ANG WARNING ---
            $found_loans = array_map(function($loan) use ($pdo) {
                $loan['total_paid'] = calculateTotalPaid($pdo, $loan['id']);
                $loan['remaining_balance'] = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']) - $loan['total_paid'];
                return $loan;
            }, $raw_loans);
            // Ensure $loan_details is null to trigger the list display in HTML
            $loan_details = null;
        }
    }
}


// Fetch notifications for header (standard inclusion)
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
    <title>Record Payment - CARD RBI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }
        .sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; position: fixed; }
        .logo-box {
            display: flex;
            justify-content: left;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .logo-box img {
            height: 60px;
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
        
        /* Form Styles */
        .search-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-form input, .search-form button { padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 16px; }
        .search-form button { background: #10b981; color: white; cursor: pointer; border: none; transition: background 0.2s; }
        .search-form button:hover { background: #059669; }
        .loan-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .detail-box { padding: 15px; border-radius: 8px; background: #f1f5f9; border-left: 5px solid #3b82f6; }
        .detail-box.financial { background: #ecfdf5; border-left: 5px solid #059669; }
        .detail-box strong { display: block; margin-bottom: 5px; color: #475569; }
        .detail-box span { font-size: 18px; font-weight: 600; color: #1e293b; }
        
        .payment-form label { display: block; margin-top: 10px; font-weight: 500; }
        .payment-form input[type="date"], 
        .payment-form input[type="number"], 
        .payment-form select { 
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 16px; 
        }
        .payment-form button { 
            margin-top: 20px; 
            width: 100%; 
            padding: 12px; 
            background: #2563eb; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            font-size: 18px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: background 0.2s; 
        }
        .payment-form button:disabled { background: #94a3b8; cursor: not-allowed; }

        .payment-form button:hover { background: #1d4ed8; }

        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 600; text-align: center; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
        .error { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }

        .warning { background: #fffbeb; color: #d97706; border: 1px solid #f59e0b; }
        
        .payments-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .payments-table th, .payments-table td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .payments-table th { background: #f1f5f9; }
        .payments-table tr:last-child td { border-bottom: none; }

        /* Loan Selection List Styling */
        .loan-selection-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: 20px; }
        .loan-selection-table th, .loan-selection-table td { padding: 12px 15px; border: none; text-align: left; background: #f8fafc; }
        .loan-selection-table th { background: #e2e8f0; font-weight: 600; color: #475569; }
        .loan-selection-table td { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); vertical-align: middle; }
        .loan-selection-table button {
            background: #22c55e;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            font-weight: 600;
        }
        .loan-selection-table button:hover { background: #15803d; }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
        <a href="staff_dashboard.php">🏠 Home</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="manage_members.php">👥 Manage Members</a>
            <a href="manage_loans.php">💼 Manage Loans</a>
            <a href="create_user.php">➕ Create Staff / Manager</a>
        <?php endif; ?>
        <a href="record_payment.php" class="active">💰 Record Payments</a>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
            <a href="generate_reports.php">📊 Reports</a>
        <?php endif; ?>
        <a href="index.php?logout=1">🚪 Logout</a>
    </aside>

    <!-- Main -->
    <main class="main">
        <header>
            <h1>💰 Record Loan Payment</h1>
            <div class="header-right">
                <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
            </div>
        </header>
        
        <?php if (!empty($msg)): ?>
            <div class="message <?= strpos($msg, 'Error') !== false ? 'error' : 'success' ?>"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Search Loan Card -->
        <div class="card">
            <h3>🔍 Search Active Loan (ID or Client Name)</h3>
            <form method="post" class="search-form">
                <input type="text" name="search_term" placeholder="Enter Loan ID or Client Name" required value="<?= isset($search_term) ? htmlspecialchars($search_term) : '' ?>">
                <button type="submit">Search Loan</button>
            </form>
        </div>

        <?php if (!empty($found_loans)): ?>
            <!-- Loan Selection List Card (If multiple results found by name) -->
            <div class="card">
                <h3>Pumili ng Tamang Loan (Multiple Active Loans Found)</h3>
                <form method="post">
                    <table class="loan-selection-table">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Loan ID</th>
                                <th>Principal / Term</th>
                                <th>Remaining Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($found_loans as $loan): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($loan['member_name']) ?></strong></td>
                                    <td><?= $loan['id'] ?></td>
                                    <td>₱<?= number_format($loan['amount'], 2) ?> / <?= $loan['term_months'] ?> mos</td>
                                    <td><span style="color: #dc2626; font-weight: 600;">₱<?= number_format($loan['remaining_balance'], 2) ?></span></td>
                                    <td>
                                        <button type="submit" name="select_loan_id" value="<?= $loan['id'] ?>">Select</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($loan_details): ?>
            <!-- Loan Details and Payment Form Card -->
            <div class="card">
                <h3>Loan Details for ID: <?= $loan_details['id'] ?> (<?= htmlspecialchars($loan_details['member_name']) ?>)</h3>

                <!-- Client and Loan Information -->
                <div class="loan-details-grid">
                    <div class="detail-box">
                        <strong>Client Name</strong>
                        <span><?= htmlspecialchars($loan_details['member_name']) ?></span>
                    </div>
                    <div class="detail-box">
                        <strong>Client Contact</strong>
                        <span><?= htmlspecialchars($loan_details['phone']) ?></span>
                    </div>
                    <div class="detail-box">
                        <strong>Loan Status</strong>
                        <span style="color:<?= $loan_details['status'] === 'defaulted' ? '#dc2626' : '#059669' ?>;"><?= ucfirst($loan_details['status']) ?></span>
                    </div>
                    <div class="detail-box">
                        <strong>Loan Term</strong>
                        <span><?= $loan_details['term_months'] ?> months @ <?= $loan_details['interest_rate'] ?>%</span>
                    </div>

                    <!-- Financial Details -->
                    <div class="detail-box financial">
                        <strong>Principal Amount</strong>
                        <span>₱<?= number_format($loan_details['amount'], 2) ?></span>
                    </div>
                    <div class="detail-box financial">
                        <strong>Total Payable (Est.)</strong>
                        <span>₱<?= number_format($loan_details['total_payable'], 2) ?></span>
                    </div>
                    <div class="detail-box financial">
                        <strong>Total Paid</strong>
                        <span>₱<?= number_format($loan_details['total_paid'], 2) ?></span>
                    </div>
                    <div class="detail-box financial">
                        <strong>Remaining Balance</strong>
                        <span style="color: #dc2626;">₱<?= number_format($loan_details['remaining_balance'], 2) ?></span>
                    </div>
                </div>

                <div class="loan-details-grid" style="margin-top: 20px;">
                    <!-- Record Payment Form -->
                    <div class="card" style="border: 1px solid #e2e8f0; padding: 15px;">
                        <h3>📥 Record New Payment</h3>
                        <form method="post" class="payment-form">
                            <input type="hidden" name="loan_id" value="<?= $loan_details['id'] ?>">

                            <label for="payment_date">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" required value="<?= date('Y-m-d') ?>">

                            <label for="amount">Amount Paid (₱)</label>
                            <input type="number" name="amount" id="amount" step="0.01" min="1.00" required placeholder="e.g., 500.00">

                            <label for="method">Payment Method</label>
                            <select name="method" id="method" required>
                                <option value="Cash">Cash</option>
                                <option value="Gcash">Gcash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>

                            <?php 
                                // Disable payment button if remaining balance is zero
                                $disabled = ($loan_details['remaining_balance'] <= 0) ? 'disabled' : '';
                                $button_text = ($loan_details['remaining_balance'] <= 0) ? 'Loan Fully Paid' : 'Record Payment Now';
                            ?>
                            <button type="submit" name="record_payment" <?= $disabled ?>><?= $button_text ?></button>

                            <?php if ($loan_details['remaining_balance'] <= 0): ?>
                                <p style="color:#1d4ed8; text-align:center; margin-top:10px;">This loan appears to be fully paid.</p>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Recent Payments Table -->
                    <div class="card" style="border: 1px solid #e2e8f0; padding: 15px;">
                        <h3>🗓️ Recent Payments (Last 5)</h3>
                        <?php if (!empty($loan_details['recent_payments'])): ?>
                            <table class="payments-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loan_details['recent_payments'] as $payment): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                            <td>₱<?= number_format($payment['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($payment['method']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center; padding:10px; color:#64748b;">No payments recorded yet for this loan.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
