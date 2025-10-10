<?php
session_start();
require 'db.php'; // Assume this file connects to $pdo

// 1. Authentication and Authorization Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$loan_id = filter_input(INPUT_GET, 'loan_id', FILTER_VALIDATE_INT);

// 2. Core Function to Calculate Outstanding Balance
// NOTE: Assuming 'total_payable' is calculated and stored in 'loans' table upon approval.
// If 'total_payable' is not stored, this needs to be adjusted.
function calculateOutstandingBalance($pdo, $loanId) {
    // A. Fetch Loan Details (Principal + Total Payable)
    $stmt = $pdo->prepare("SELECT amount, total_payable FROM loans WHERE id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan) {
        return null; // Loan not found
    }
    
    // Use the total_payable field if it exists. If not, calculate Simple Add-on Interest
    // ASSUMPTION: The Total Payable (Principal + Interest) is stored in the DB.
    // If not, use the fallback Simple Add-on Interest calculation (e.g., 2% per month for 6 months = 12% total)
    
    // *** You need to adjust this calculation based on where your final loan terms are stored ***
    // For now, let's use a safe assumption based on industry standards if total_payable is NULL/missing.
    $total_loan_amount = $loan['total_payable'] ?? ($loan['amount'] * 1.12); // Fallback: 12% total interest (2% x 6 months)

    // B. Fetch Total Payments Made
    $stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE loan_id = ?");
    $stmt->execute([$loanId]);
    $payment_data = $stmt->fetch();
    $total_paid = $payment_data['total_paid'] ?? 0;

    // C. Calculate Outstanding Balance (Rounded to prevent float errors)
    $outstanding_balance = round($total_loan_amount - $total_paid, 2);

    return [
        'balance' => $outstanding_balance,
        'total_payable' => round($total_loan_amount, 2)
    ];
}


// 3. Handle Payment Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loan_id) {
    // Sanitize and Validate Inputs
    $payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);
    $payment_method = trim($_POST['method']);

    if ($payment_amount <= 0 || empty($payment_method)) {
        $msg = "⚠ Invalid payment amount or method.";
    } else {
        $balance_data = calculateOutstandingBalance($pdo, $loan_id);
        $current_balance = $balance_data['balance'];
        
        // --- CRITICAL FIX: Ghost Payment Prevention ---
        if ($current_balance <= 0) {
            $msg = "❌ Error: This loan is already fully settled and has no outstanding balance.";
        } else {
            
            // Determine the actual amount to be paid (cannot exceed the outstanding balance)
            $actual_payment_to_record = min($payment_amount, $current_balance);
            $new_balance = round($current_balance - $actual_payment_to_record, 2);

            try {
                $pdo->beginTransaction();

                // 3a. Record the Payment Transaction
                $stmt = $pdo->prepare("
                    INSERT INTO payments (loan_id, amount, method, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$loan_id, $actual_payment_to_record, $payment_method]);
                
                $msg = "✅ Payment of ₱" . number_format($actual_payment_to_record, 2) . " successfully recorded!";
                
                // 3b. Update Loan Status if fully paid
                if ($new_balance <= 0) {
                    $stmt = $pdo->prepare("UPDATE loans SET status = 'paid', date_paid = NOW() WHERE id = ?");
                    $stmt->execute([$loan_id]);
                    $msg .= " The loan is now **FULLY PAID**!";
                }

                // 3c. Log the transaction for audit (assuming a 'logs' table)
                $log_description = "Payment of ₱" . number_format($actual_payment_to_record, 2) . " recorded for Loan #{$loan_id}. New Balance: ₱" . number_format($new_balance, 2);
                $stmt = $pdo->prepare("INSERT INTO logs (user_id, action_description, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $log_description]);
                
                $pdo->commit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $msg = "❌ Payment Error: " . $e->getMessage();
            }
        }
    }
}

// 4. Fetch Loan Data for Display
$loan_details = null;
$balance_info = null;

if ($loan_id) {
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$loan_id]);
    $loan_details = $stmt->fetch();
    
    if ($loan_details) {
        // Recalculate balance for display after any payment
        $balance_info = calculateOutstandingBalance($pdo, $loan_id);
    } else {
        $msg = "❌ Loan not found.";
        $loan_id = null;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Make Payment</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#f8fafc; color:#1e293b; padding:20px; }
.container { background:white; max-width:600px; margin:20px auto; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
h2 { color:#0f172a; border-bottom:2px solid #e2e8f0; padding-bottom:10px; margin-top:0; }
.loan-summary div { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #f1f5f9; }
.loan-summary strong { color:#0f172a; }
.loan-summary span { color:#475569; }
.balance-info { margin:20px 0; padding:15px; border-radius:8px; background:#f0f9ff; border-left:5px solid #0ea5e9; }
.balance-info h3 { margin:0 0 5px 0; color:#0ea5e9; }
.balance-amount { font-size:1.8rem; font-weight:700; color:#dc2626; }
form { margin-top:20px; }
label { display:block; margin-top:15px; font-weight:600; color:#1e293b; }
input, select { width:100%; padding:12px; margin-top:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; }
button { margin-top:25px; padding:12px 25px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; width:100%; font-size:1rem; font-weight:600; transition:background 0.2s; }
button:hover { background:#1d4ed8; }
.msg, .error { padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600; }
.msg { background:#dcfce7; color:#166534; }
.error { background:#fee2e2; color:#991b1b; }
.btn-back { display:inline-block; margin-top:20px; color:#64748b; text-decoration:none; }
.btn-back:hover { color:#475569; }
</style>
</head>
<body>

<div class="container">
    <h2>💰 Record Payment for Loan #<?= htmlspecialchars($loan_id) ?></h2>

    <?php if(!empty($msg)): ?>
        <div class="<?= (strpos($msg, '✅') !== false) ? 'msg' : 'error' ?>">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if ($loan_details && $balance_info): ?>
        <div class="loan-summary">
            <div>
                <strong>Principal Loan Amount:</strong>
                <span>₱<?= number_format($loan_details['amount'] ?? 0, 2) ?></span>
            </div>
            <div>
                <strong>Total Payable (Principal + Interest):</strong>
                <span>₱<?= number_format($balance_info['total_payable'], 2) ?></span>
            </div>
        </div>

        <div class="balance-info">
            <h3>Outstanding Balance:</h3>
            <div class="balance-amount">₱<?= number_format($balance_info['balance'], 2) ?></div>
        </div>
        
        <?php if ($loan_details['status'] === 'paid'): ?>
            <div class="msg" style="background:#fefce8; color:#a16207;">
                This loan is already fully settled as of <?= htmlspecialchars($loan_details['date_paid'] ?? 'N/A') ?>.
            </div>
        <?php elseif ($balance_info['balance'] > 0): ?>
            <form method="post">
                <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan_id) ?>">
                
                <label for="payment_amount">Payment Amount (₱)</label>
                <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" max="<?= htmlspecialchars($balance_info['balance']) ?>" required>

                <label for="method">Payment Method</label>
                <select name="method" id="method" required>
                    <option value="">-- Select Method --</option>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Online Payment">Online Payment</option>
                </select>

                <button type="submit">Submit Payment</button>
            </form>
        <?php endif; ?>

    <?php else: ?>
        <div class="error">
            Could not retrieve loan details. Please verify the Loan ID.
        </div>
    <?php endif; ?>
    
    <a href="client_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
