<?php
// Tiyakin na ito ang UNANG-UNANG LINE sa file. Walang space o newline sa taas.
session_start();
require 'db.php';
require 'auth.php'; // Assuming auth.php handles session start and current_user()
$user = current_user();

// Tiyakin na client lang ang may access
if ($user['role'] !== 'client') {
    header("Location: index.php?access_denied=1");
    exit;
}

// 1. Kumuha ng Loan ID mula sa URL
$loan_id = filter_input(INPUT_GET, 'loan_id', FILTER_VALIDATE_INT);
if (!$loan_id) {
    header("Location: my_loans.php?error=" . urlencode('Invalid Loan ID.'));
    exit;
}

// 2. Kumuha ng Member ID
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([$user['id']]);
$member = $stmt->fetch();
if (!$member) {
    header("Location: client_dashboard.php?error=" . urlencode('Member record not found.'));
    exit;
}
$member_id = $member['id'];

// 3. Kumuha ng Loan Details at tiyakin na ito ay pag-aari ng client
$stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND member_id = ?");
$stmt->execute([$loan_id, $member_id]);
$loan = $stmt->fetch();

if (!$loan) {
    header("Location: my_loans.php?error=" . urlencode('Loan not found or access denied.'));
    exit;
}

// 4. Kumuha ng Total Payments Made
$stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE loan_id = ? AND status='verified'");
$stmt->execute([$loan_id]);
$total_payments_verified = floatval($stmt->fetchColumn() ?: 0.00);

// =========================================================
// AMORTIZATION CALCULATIONS (Simple Interest)
// =========================================================

$amount = $loan['amount'];
$term = $loan['term_months'];
$rate = $loan['interest_rate'];
$loan_start_date = $loan['loan_start'] ? new DateTime($loan['loan_start']) : null;

// Calculate basic details
$total_interest = $amount * ($rate / 100) * ($term / 12);
$total_payable = $amount + $total_interest;
$monthly_due = $total_payable / $term;
$monthly_principal = $amount / $term;
$monthly_interest = $total_interest / $term;

$amortization_schedule = [];
$current_principal_balance = $amount;
$current_payments_made = $total_payments_verified;
$next_due_date = null;
$next_due_amount = 0;

if ($loan_start_date) {
    for ($i = 1; $i <= $term; $i++) {
        // Calculate due date for this installment (start date + i months)
        $due_date = clone $loan_start_date;
        $due_date->add(new DateInterval("P{$i}M"));
        $due_date_str = $due_date->format('Y-m-d');

        // Check if installment is paid (A simplification: assume full payment order)
        $is_paid = $current_payments_made >= $monthly_due;
        
        $status = 'Pending';
        if ($is_paid) {
            $status = 'Paid';
            $current_payments_made -= $monthly_due;
        } elseif (!$next_due_date) {
            // Ito ang unang installment na hindi pa paid
            $status = 'Due Soon';
            $next_due_date = $due_date_str;
            $next_due_amount = $monthly_due;
        } elseif ($due_date < new DateTime()) {
            // Kung wala nang next_due_date pero nakalipas na ang date, ito ay Defaulted (simple check)
            $status = 'Defaulted';
        }

        // Calculate Remaining Balance for display
        $balance_after_payment = $current_principal_balance - $monthly_principal;
        
        $amortization_schedule[] = [
            'installment' => $i,
            'due_date' => $due_date_str,
            'principal' => $monthly_principal,
            'interest' => $monthly_interest,
            'due_amount' => $monthly_due,
            'status' => $status,
            'remaining_balance' => max($balance_after_payment, 0.00) // Ensure not negative
        ];
        
        if ($status !== 'Paid') {
            // Stop updating principal balance if payments have stopped covering installments
             $current_principal_balance = $current_principal_balance;
        } else {
            $current_principal_balance = $balance_after_payment;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loan #<?= $loan_id ?> Details</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#f8fafc; color:#1e293b; padding:20px; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
h1 { color:#1e3a8a; border-bottom: 2px solid #e0e7ff; padding-bottom: 10px; margin-bottom: 20px;}
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.summary-card { padding: 15px; border-radius: 8px; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.summary-card.total { background: #e0f2fe; border-left: 5px solid #3b82f6; }
.summary-card.paid { background: #dcfce7; border-left: 5px solid #10b981; }
.summary-card.balance { background: #fee2e2; border-left: 5px solid #ef4444; }
.summary-card h3 { font-size: 16px; color: #475569; margin-bottom: 5px; font-weight: 500; }
.summary-card p { font-size: 20px; font-weight: 700; color: #1e3a8a; }

.alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
.alert-box.success { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
.alert-box.warning { background: #fef9c3; color: #b45309; border: 1px solid #fde047; }
.alert-box.error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }

/* Table Styling */
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:10px; border:1px solid #e2e8f0; text-align:right; font-size: 13px; }
th { background:#f1f5f9; color: #475569; font-weight: 600; text-align: center; }
td:first-child, th:first-child { text-align: center; }
td.status-Paid { background: #ecfdf5; color: #065f46; font-weight: 600; }
td.status-Due { background: #fffbeb; color: #92400e; font-weight: 600; }
td.status-DueSoon { background: #e0f2fe; color: #1e40af; font-weight: 600; }
td.status-Defaulted { background: #fef2f2; color: #991b1b; font-weight: 600; }
.back-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 15px;
    background: #475569;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.2s;
}
.back-btn:hover { background: #1e293b; }
</style>
</head>
<body>

<div class="container">
    <h1>📄 Loan Details - #<?= $loan_id ?></h1>

    <!-- Alert Box for Next Due Date -->
    <?php if ($loan['status'] === 'ongoing' && $next_due_date): ?>
        <div class="alert-box warning">
            🔔 **UPCOMING PAYMENT DUE:** ₱<?= number_format($next_due_amount, 2) ?> on **<?= date('F d, Y', strtotime($next_due_date)) ?>**.
        </div>
    <?php elseif ($loan['status'] === 'approved' && !$loan_start_date): ?>
        <div class="alert-box success">
            ✅ **APPROVED!** Your loan is ready for disbursal. Start date will be recorded soon.
        </div>
    <?php elseif ($loan['status'] === 'closed'): ?>
        <div class="alert-box success">
            🎉 **CLOSED!** This loan is fully paid.
        </div>
    <?php elseif ($loan['status'] === 'defaulted'): ?>
        <div class="alert-box error">
            ⚠️ **DEFAULTED!** Please contact your Loan Officer immediately.
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card total">
            <h3>Loan Principal</h3>
            <p>₱<?= number_format($amount, 2) ?></p>
        </div>
        <div class="summary-card total">
            <h3>Total Payable</h3>
            <p>₱<?= number_format($total_payable, 2) ?></p>
        </div>
        <div class="summary-card paid">
            <h3>Verified Payments</h3>
            <p>₱<?= number_format($total_payments_verified, 2) ?></p>
        </div>
        <div class="summary-card balance">
            <h3>Remaining Balance</h3>
            <p>₱<?= number_format(max($total_payable - $total_payments_verified, 0), 2) ?></p>
        </div>
    </div>
    
    <h2>📊 Amortization Schedule (<?= $loan['term_months'] ?> Months)</h2>

    <?php if (!$loan_start_date): ?>
        <p style="padding: 15px; background: #fef9c3; border: 1px solid #fde047; border-radius: 6px;">
            The amortization schedule will appear here once the loan **Disbursal Date** (Loan Start Date) has been recorded by the Staff.
        </p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Installment #</th>
                    <th>Due Date</th>
                    <th>Monthly Principal</th>
                    <th>Monthly Interest</th>
                    <th>Total Monthly Due</th>
                    <th>Remaining Principal Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($amortization_schedule as $item): ?>
                <tr>
                    <td><?= $item['installment'] ?></td>
                    <td><?= date('M d, Y', strtotime($item['due_date'])) ?></td>
                    <td>₱<?= number_format($item['principal'], 2) ?></td>
                    <td>₱<?= number_format($item['interest'], 2) ?></td>
                    <td>₱<?= number_format($item['due_amount'], 2) ?></td>
                    <td>₱<?= number_format($item['remaining_balance'], 2) ?></td>
                    <td class="status-<?= str_replace(' ', '', $item['status']) ?>"><?= $item['status'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <a href="my_loans.php" class="back-btn">⬅ Back to Loan History</a>
</div>

</body>
</html>
