<?php
require_once 'auth.php';
require_once 'db.php';

$user = current_user();

// Allow both staff and client
if (!in_array($user['role'], ['client', 'staff'])) {
    header("Location: index.php");
    exit;
}

// =========================================================
// LOAN CALCULATION UTILITY (CRITICAL: Must include interest)
// =========================================================
function calculateTotalPayable($principal, $rate, $term_months) {
    // Convert term to years
    $term_in_years = $term_months / 12;
    // Simple Interest Formula: Principal * Rate * Time(in years)
    $interest_amount = $principal * ($rate / 100) * $term_in_years; 
    
    // Total Payable = Principal + Interest Amount
    return round($principal + $interest_amount, 2);
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

// =========================================================
// LOAN AND PAYMENT FETCHING
// =========================================================

// ✅ For staff — select all verified payments
if ($user['role'] === 'staff') {
    $stmt = $pdo->query("
        SELECT p.*, m.name AS member_name, l.amount AS loan_principal, l.interest_rate, l.term_months
        FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        JOIN members m ON l.member_id = m.id 
        WHERE p.status = 'verified'
        ORDER BY p.payment_date DESC
    ");
} else {
    // ✅ For clients — select their own verified payments
    $stmt = $pdo->prepare("
        SELECT p.*, l.amount AS loan_principal, l.interest_rate, l.term_months
        FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        WHERE l.member_id = ? AND p.status = 'verified'
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$member['id']]);
}
$payments = $stmt->fetchAll();

// =========================================================
// CLIENT OUTSTANDING BALANCE CALCULATION (INCLUDING INTEREST)
// =========================================================
$outstanding = 0;
if ($user['role'] === 'client' && $member) {
    // 1. Fetch all *approved/ongoing/defaulted* loans for the member
    $active_loans_stmt = $pdo->prepare("
        SELECT id, amount, interest_rate, term_months
        FROM loans 
        WHERE member_id = ? 
        AND status IN ('approved', 'ongoing', 'defaulted')
    ");
    $active_loans_stmt->execute([$member['id']]);
    $active_loans_data = $active_loans_stmt->fetchAll();

    foreach ($active_loans_data as $loan) {
        $loan_id = $loan['id'];
        
        // Calculate the Total Payable (Principal + Interest)
        $total_payable = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);

        // Sum of all VERIFIED payments for this specific loan
        $total_paid_loan_stmt = $pdo->prepare("
            SELECT SUM(amount) AS loan_paid 
            FROM payments 
            WHERE loan_id = ? AND status = 'verified' 
        ");
        $total_paid_loan_stmt->execute([$loan_id]);
        $loan_paid = $total_paid_loan_stmt->fetchColumn() ?? 0;
        
        // Outstanding = Total Payable - Total Paid (Ensure it's not negative)
        $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);
        $outstanding += $current_outstanding_for_loan;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Reset and Base Styles */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{display:flex;background:#f8fafc;color:#1e293b;}

/* Sidebar Styles */
.sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
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
.sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
.sidebar a:hover{background:#1e293b;color:#fff;}
.logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
.logout:hover{background:#b91c1c;}

/* Main Content Styles */
.main{flex:1;padding:30px 40px;}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
header h1{font-size:28px;font-weight:700;color:#1e3a8a;}
.profile{background:#e0f2fe;color:#1e3a8a;padding:8px 15px;border-radius:8px;font-weight:500;}
.card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:25px;}
.card h2{font-size:20px;margin-bottom:15px;color: #334155;}

/* Balance Styling */
.balance-section { display: flex; justify-content: space-between; align-items: center; }
.balance{font-weight:700;color:#dc2626;font-size:32px;}
.balance-label { font-size: 16px; color: #64748b; }


/* Table Styles */
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;font-weight:600;color: #1e293b;}
.no-data{text-align:center;padding:20px;color:#64748b;}
.green-btn {background: #10b981; margin-right: 0; padding: 10px 15px; border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;}
.green-btn:hover {background: #059669;}
.download-btn {background: #475569; padding: 10px 15px; border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;}
.download-btn:hover {background: #334155;}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo-box">
      <!-- Placeholder logo -->
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <?php if($user['role']=='client'): ?>
        <a href="client_dashboard.php">🏠 Home</a>
        <a href="request_loan.php">💸 Request Loan</a>
        <a href="my_loans.php">💼 My Loans</a>
        <a href="my_payments.php" style="background:#1e293b; color:#fff;">💰 My Payments</a>
        <a href="upload_photo.php">📸 Upload Proof</a>
        <a href="my_history.php">📜 My History</a>
    <?php else: ?>
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="members.php">👥 Members</a>
        <a href="loan_approvals.php">✅ Loan Approvals</a>
        <a href="record_payment.php">💰 Record Payment</a> 
    <?php endif; ?>
    
</aside>

<!-- Main -->
<main class="main">
    <header>
        <h1>💰 Payment History</h1>
        <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</div>
    </header>

    <?php if($user['role']=='client' && $member): ?>
    <div class="card">
        <div class="balance-section">
            <div>
                <h2>💵 Outstanding Balance</h2>
                <p class="balance-label">Total remaining amount including principal and interest:</p>
            </div>
            <!-- Display the accurately calculated outstanding balance -->
            <p><span class="balance">₱<?= number_format(max($outstanding, 0), 2) ?></span></p>
        </div>
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e5e7eb;">
        
        <div style="display: flex; gap: 10px;">
            <button onclick="window.location='process_payment.php'" class="green-btn" style="flex: 1;">Make a Payment</button>
            <button onclick="window.location='export_t_pdf.php'" class="download-btn" style="flex: 1;">Download Receipt (PDF)</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>📋 Verified Payment History</h2>
        <table>
            <tr>
                <th>Date</th>
                <!-- <th>Loan ID</th> Removed Loan ID column header -->
                <th>Amount (₱)</th>
                <th>Method</th>
                <?php if($user['role']=='staff') echo "<th>Member</th>"; ?>
            </tr>
            <?php if ($payments): ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($p['payment_date']))) ?></td>
                        <!-- <td><?= htmlspecialchars($p['loan_id']) ?></td> Removed Loan ID data cell -->
                        <td><?= number_format($p['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['method']) ?></td>
                        <?php if($user['role']=='staff'): ?>
                            <td><?= htmlspecialchars($p['member_name']) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?= $user['role']=='staff' ? 4 : 3 ?>" class="no-data">No verified payments yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>

</main>
</body>
</html>