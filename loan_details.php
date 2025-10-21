<?php
require_once 'auth.php';
require_once 'db.php';

$user = current_user();

// Client-only access
if ($user['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

// Ensure a loan ID is provided in the URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: my_loans.php"); // Redirect if no valid ID
    exit;
}

$loan_id = $_GET['id'];

// Get linked member record
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user['id']]);
$member = $stmt->fetch();

if (!$member) {
    die("❌ Error: Your account is not linked to any member record. Please contact support.");
}

$member_id = $member['id'];

// Fetch specific loan details for this client
$stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND member_id = ?");
$stmt->execute([$loan_id, $member_id]);
$loan = $stmt->fetch();

if (!$loan) {
    header("Location: my_loans.php"); // Redirect if loan not found or doesn't belong to user
    exit;
}

// =========================================================
// LOAN CALCULATION UTILITY (Copied from my_loans.php for consistency)
// =========================================================
function calculateTotalPayable($principal, $rate, $term_months) {
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return round($principal + $interest_amount, 2);
}

// Calculate additional details if applicable
$total_payable = 0;
$total_paid = 0;
$remaining_balance = 0;
$payments = []; // To store all payments for this loan

if (in_array($loan['status'], ['approved', 'ongoing', 'defaulted', 'fully paid'])) {
    $total_payable = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);

    // Fetch all verified payments for this loan
    $payments_stmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE loan_id = ? AND status = 'verified'
        ORDER BY payment_date DESC
    ");
    $payments_stmt->execute([$loan_id]);
    $payments = $payments_stmt->fetchAll();

    foreach ($payments as $payment) {
        $total_paid += $payment['amount'];
    }

    $remaining_balance = max(0, $total_payable - $total_paid);
}

// Function for status color coding (Copied from my_loans.php)
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'color: #f59e0b; font-weight: 600;'; // Amber
        case 'approved': return 'color: #10b981; font-weight: 600;'; // Green
        case 'ongoing': return 'color: #2563eb; font-weight: 600;'; // Blue
        case 'rejected': return 'color: #dc2626; font-weight: 600;'; // Red
        case 'fully paid': return 'color: #059669; font-weight: 600;'; // Darker Green
        case 'cancelled': return 'color: #64748b; font-weight: 600;'; // Gray
        case 'defaulted': return 'color: #ef4444; font-weight: 700;'; // Light Red (Urgent)
        default: return 'color: #334155;';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loan Details</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Reset and Base Styles (Matches your existing style) */
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

/* Details List Styles */
.detail-list {
    list-style: none;
    padding: 0;
    margin-bottom: 20px;
}
.detail-list li {
    padding: 8px 0;
    border-bottom: 1px dashed #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.detail-list li:last-child {
    border-bottom: none;
}
.detail-list strong {
    color: #334155;
    min-width: 150px; /* Adjust as needed */
}
.detail-list span {
    text-align: right;
    flex-grow: 1;
}

/* Table Styles */
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;font-weight:600;color: #1e293b;}
.no-data{text-align:center;padding:20px;color:#64748b;}

/* Button Styles */
.back-btn {
    background: #64748b; /* Gray */
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    display: inline-block;
    margin-top: 20px;
    transition: background 0.3s ease;
}
.back-btn:hover {
    background: #475569; /* Darker gray */
}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php" style="background:#1e293b; color:#fff;">💼 My Loans</a>
    <a href="my_payments.php">💰 My Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php">📜 My History</a>
</aside>

<!-- Main -->
<main class="main">
    <header>
        <h1>📄 Loan Details</h1>
        <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</div>
    </header>

    <div class="card">
        <h2>Loan Information</h2>
        <ul class="detail-list">
            <li><strong>Loan Amount:</strong> <span>₱<?= number_format($loan['amount'], 2) ?></span></li>
            <li><strong>Interest Rate:</strong> <span><?= htmlspecialchars($loan['interest_rate']) ?>%</span></li>
            <li><strong>Term:</strong> <span><?= htmlspecialchars($loan['term_months']) ?> Months</span></li>
            <li><strong>Application Date:</strong> <span><?= htmlspecialchars(date('M d, Y', strtotime($loan['created_at']))) ?></span></li>
            <li><strong>Status:</strong> <span style="<?= getStatusColor($loan['status']) ?>"><?= htmlspecialchars(ucfirst($loan['status'])) ?></span></li>
            <?php if ($loan['status'] === 'rejected' && !empty($loan['rejection_reason'])): ?>
                <li><strong>Rejection Reason:</strong> <span><?= htmlspecialchars($loan['rejection_reason']) ?></span></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (in_array($loan['status'], ['approved', 'ongoing', 'defaulted', 'fully paid'])): ?>
        <div class="card">
            <h2>Financial Overview</h2>
            <ul class="detail-list">
                <li><strong>Calculated Total Payable:</strong> <span>₱<?= number_format($total_payable, 2) ?></span></li>
                <li><strong>Total Payments Verified:</strong> <span>₱<?= number_format($total_paid, 2) ?></span></li>
                <li><strong>Remaining Balance:</strong> <span style="color: <?= $remaining_balance > 0 ? '#dc2626' : '#059669' ?>;">₱<?= number_format($remaining_balance, 2) ?></span></li>
            </ul>
        </div>

        <div class="card">
            <h2>Payment History</h2>
            <table>
                <tr>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Reference No.</th>
                    <th>Proof</th>
                </tr>
                <?php if ($payments): ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($payment['payment_date']))) ?></td>
                            <td><?= htmlspecialchars($payment['method']) ?></td>
                            <td>₱<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($payment['proof_of_payment_path'])): ?>
                                    <a href="<?= htmlspecialchars($payment['proof_of_payment_path']) ?>" target="_blank">View Proof</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="no-data">No verified payments found for this loan.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

    <a href="my_loans.php" class="back-btn">← Back to My Loans</a>

</main>
</body>
</html>