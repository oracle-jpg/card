<?php
require_once 'auth.php';
require_once 'db.php';

$user = current_user();

// Client-only access (Staff has a different view)
if ($user['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

// =========================================================
// LOAN CALCULATION UTILITY (CRITICAL: Must include interest)
// =========================================================
// This is the FIRST and CORRECT place to declare calculateTotalPayable()
function calculateTotalPayable($principal, $rate, $term_months) {
    // Convert term to years
    $term_in_years = $term_months / 12;
    // Simple Interest Formula: Principal * Rate * Time(in years)
    $interest_amount = $principal * ($rate / 100) * $term_in_years; 
    
    // Total Payable = Principal + Interest Amount
    return round($principal + $interest_amount, 2);
}

// ✅ Get linked member record
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user['id']]);
$member = $stmt->fetch();

if (!$member) {
    die("❌ Error: Your account is not linked to any member record. Please contact support.");
}

$member_id = $member['id'];
$message = '';

// =========================================================
// HANDLE LOAN CANCELLATION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_loan_id'])) {
    $loan_id = $_POST['cancel_loan_id'];

    // 1. Check if the loan belongs to the member and is in 'pending' status
    $stmt = $pdo->prepare("SELECT status FROM loans WHERE id = ? AND member_id = ?");
    $stmt->execute([$loan_id, $member_id]);
    $loan_status_check = $stmt->fetchColumn();

    if ($loan_status_check === 'pending') {
        // 2. Update status to 'cancelled'
        $update_stmt = $pdo->prepare("UPDATE loans SET status = 'cancelled' WHERE id = ?");
        if ($update_stmt->execute([$loan_id])) {
            $message = "✅ Loan ID $loan_id has been successfully cancelled.";
        } else {
            $message = "❌ Error cancelling loan: Database update failed.";
        }
    } else {
        $message = "⚠️ Cannot cancel Loan ID $loan_id. It is either not pending or does not belong to your account.";
    }
}

// =========================================================
// FETCH ALL LOANS FOR THE CLIENT
// =========================================================
$stmt = $pdo->prepare("
    SELECT * FROM loans 
    WHERE member_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$member_id]);
$loans = $stmt->fetchAll();

// Array to hold computed loan details (Total Payable, Paid, Remaining)
$loan_details = [];

foreach ($loans as $loan) {
    $loan_id = $loan['id'];
    $details = $loan; // Start with base loan data

    if (in_array($loan['status'], ['approved', 'ongoing', 'defaulted', 'fully paid'])) {
        // Calculate the Total Payable (Principal + Interest)
        $total_payable = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);

        // Sum of all VERIFIED payments for this specific loan
        $total_paid_stmt = $pdo->prepare("
            SELECT SUM(amount) AS loan_paid 
            FROM payments 
            WHERE loan_id = ? AND status = 'verified'
        ");
        $total_paid_stmt->execute([$loan_id]);
        $total_paid = $total_paid_stmt->fetchColumn() ?? 0;
        
        // Calculate Remaining Balance
        $remaining_balance = max(0, $total_payable - $total_paid);
        
        $details['total_payable'] = $total_payable;
        $details['total_paid'] = $total_paid;
        $details['remaining_balance'] = $remaining_balance;
    }
    
    $loan_details[] = $details;
}

// Function for status color coding
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
<title>My Loans</title>
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

/* Table Styles */
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;font-weight:600;color: #1e293b;}
.no-data{text-align:center;padding:20px;color:#64748b;}

/* Button Styles */
.cancel-btn {
    background: #f43f5e; /* Rose Red */
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s;
}
.cancel-btn:hover {
    background: #e11d48;
}

/* Status Message */
.status-message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: 500;
    border: 1px solid;
}
.status-message.success {
    background-color: #d1fae5; /* Light Green */
    border-color: #10b981; /* Green */
    color: #065f46; /* Dark Green Text */
}
.status-message.error, .status-message.warning {
    background-color: #fee2e2; /* Light Red */
    border-color: #ef4444; /* Red */
    color: #991b1b; /* Dark Red Text */
}
</style>
<script>
    function confirmCancel(loanId) {
        const modal = document.getElementById('cancelModal');
        // confirmButton variable is not used, removed if not needed:
        // const confirmButton = document.getElementById('confirmCancelButton'); 
        
        document.getElementById('loanIdToCancel').value = loanId;
        document.getElementById('modalLoanIdDisplay').textContent = loanId;
        
        modal.style.display = 'flex';
        
        modal.onclick = function(event) {
            if (event.target === modal || event.target.className === 'close-btn') {
                modal.style.display = 'none';
            }
        };
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const modalHtml = `
            <div id="cancelModal" style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4); justify-content:center; align-items:center;">
                <div style="background-color:#fff; padding:30px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.2); width:90%; max-width:400px; text-align:center;">
                    <h3 style="margin-bottom:15px; color:#ef4444;">Confirm Cancellation</h3>
                    <p style="margin-bottom:20px; color:#475569;">Are you sure you want to cancel Loan ID <span id="modalLoanIdDisplay" style="font-weight:700;"></span>? This action cannot be undone.</p>
                    <form method="POST" style="display:flex; justify-content:space-around; gap:10px;">
                        <input type="hidden" name="cancel_loan_id" id="loanIdToCancel">
                        <button type="submit" id="confirmCancelButton" class="cancel-btn" style="flex:1; background:#f43f5e;">Yes, Cancel</button>
                        <button type="button" onclick="document.getElementById('cancelModal').style.display='none'" style="flex:1; background:#94a3b8; border:none; color:white; padding:10px; border-radius:6px; cursor:pointer;">No, Keep It</button>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    });
</script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo-box">
      <!-- Placeholder logo -->
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
        <h1>💼 My Loan Records</h1>
        <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</div>
    </header>

    <?php if ($message): ?>
        <?php $msg_class = (strpos($message, '✅') !== false) ? 'success' : ((strpos($message, '❌') !== false) ? 'error' : 'warning'); ?>
        <div class="status-message <?= $msg_class ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>📝 All Loan Applications</h2>
        <table>
            <tr>
                <!-- ID column removed for client view -->
                <th>Amount (₱)</th>
                <th>Interest Rate (%)</th>
                <th>Term (Months)</th>
                <th>Status</th>
                <th>Date Applied</th>
                <th>Details</th>
                <th>Action</th>
            </tr>
            <?php if ($loan_details): ?>
                <?php foreach ($loan_details as $d): ?>
                    <tr>
                        <!-- ID column data removed -->
                        <td><?= number_format($d['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($d['interest_rate']) ?></td>
                        <td><?= htmlspecialchars($d['term_months']) ?></td>
                        <td style="<?= getStatusColor($d['status']) ?>"><?= htmlspecialchars(ucfirst($d['status'])) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($d['created_at']))) ?></td>
                        <td>
                            <?php if (in_array($d['status'], ['approved', 'ongoing', 'defaulted', 'fully paid'])): ?>
                                <p style="font-size: 13px; line-height: 1.4;">
                                    <strong>Total Payable:</strong> ₱<?= number_format($d['total_payable'], 2) ?><br>
                                    <strong>Total Paid:</strong> ₱<?= number_format($d['total_paid'], 2) ?><br>
                                    <?php if ($d['status'] !== 'fully paid'): ?>
                                        <strong style="color: <?= $d['remaining_balance'] > 0 ? '#dc2626' : '#059669' ?>;">Remaining:</strong> ₱<?= number_format($d['remaining_balance'], 2) ?>
                                    <?php else: ?>
                                        <strong style="color: #059669;">Remaining:</strong> ₱0.00 (Fully Paid)
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <em>N/A until approved.</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'pending'): ?>
                                <button onclick="confirmCancel(<?= $d['id'] ?>)" class="cancel-btn">Cancel Request</button>
                            <?php else: ?>
                                <em>N/A</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="no-data">You have not requested any loans yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>

</main>
</body>
</html>