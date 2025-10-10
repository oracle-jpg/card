<?php
session_start();
require 'db.php';
// logAudit() and notifyRole() are expected to be available from db.php

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info and check for active loan (reuse logic from dashboard)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_id = $member['id'] ?? null;

$has_active_loan = false;
$outstanding = 0; // Check if the member has an active loan with outstanding balance

// Reusing the calculation utility (simplified here, but should ideally be consistent with dashboard logic)
function calculateTotalPayable($principal, $rate, $term_months) {
    // Interest is simple interest over the term
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return round($principal + $interest_amount, 2);
}

if ($member_id) {
    $all_loans_stmt = $pdo->prepare("
        SELECT * FROM loans 
        WHERE member_id = ? 
        AND status IN ('approved', 'ongoing', 'defaulted') 
    ");
    $all_loans_stmt->execute([$member_id]);
    $active_loans = $all_loans_stmt->fetchAll();

    foreach ($active_loans as $loan) {
        $loan_id = $loan['id'];
        $total_payable = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);

        $total_paid_loan_stmt = $pdo->prepare("
            SELECT SUM(amount) AS loan_paid 
            FROM payments 
            WHERE loan_id = ? AND status = 'verified' 
        ");
        $total_paid_loan_stmt->execute([$loan_id]);
        $loan_paid = $total_paid_loan_stmt->fetchColumn() ?? 0;
        
        $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);
        
        if ($current_outstanding_for_loan > 0) {
            $has_active_loan = true;
            $outstanding += $current_outstanding_for_loan;
        }
    }
}


// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$member_id) {
        $error = "Error: Member record not found.";
    } elseif ($has_active_loan) {
        $error = "You have an existing loan with an outstanding balance of ₱" . number_format($outstanding, 2) . ". Please settle it first.";
    } else {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $term_months = filter_input(INPUT_POST, 'term_months', FILTER_VALIDATE_INT);
        $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING);
        
        // Rate calculation logic (must match calculate_loan.php)
        $final_rate = 0; 
        $MIN_RATE = 3.0;
        $MAX_RATE = 5.0; 
        $MIN_TERM = 1; 
        $MAX_TERM = 12;

        $term_months_clamped = max($MIN_TERM, min($MAX_TERM, $term_months));
        $term_range = $MAX_TERM - $MIN_TERM;
        $term_range = $term_range > 0 ? $term_range : 1; 

        $rate_range = $MAX_RATE - $MIN_RATE;
        $term_ratio = ($term_months_clamped - $MIN_TERM) / $term_range;
        $final_rate = round($MIN_RATE + ($rate_range * $term_ratio), 2);

        $calc_result = calculateTotalPayable($amount, $final_rate, $term_months);
        $total_payable = $calc_result;

        if ($amount > 0 && $term_months > 0 && $purpose) {
            try {
                // Ensure correct column name is used
                $stmt = $pdo->prepare("INSERT INTO loans (member_id, amount, interest_rate, term_months, purpose, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute([$member_id, $amount, $final_rate, $term_months, $purpose]);
                
                $loan_id = $pdo->lastInsertId();
                
                // logAudit() and notifyRole() are available from db.php
                logAudit($pdo, $user_id, 'LOAN_REQUESTED', "Requested loan ID {$loan_id}: ₱{$amount} at {$final_rate}% over {$term_months} months.");

                // Notify Admin
                notifyRole($pdo, 'admin', 'New Loan Request', "Member {$user['full_name']} requested a loan of ₱" . number_format($amount, 2) . ".");

                $message = "Your loan request (₱" . number_format($amount, 2) . " for {$term_months} months) has been submitted for review. Total expected payable: ₱" . number_format($total_payable, 2) . ".";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all required fields with valid values.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request New Loan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
body { display:flex; background:#f8fafc; color:#1e293b; }

/* Sidebar Styling (from client_dashboard.php) */
.sidebar { width:230px; background:#0f172a; color:#fff; min-height:100vh; padding:25px 20px; display:flex; flex-direction:column; }
.logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; border-radius: 8px; }
.logo-box img { height: 60px; width: auto; border-radius: 6px; }
.sidebar a { color:#e2e8f0; text-decoration:none; padding:10px; margin-bottom:8px; border-radius:6px; display:block; transition:0.3s; }
.sidebar a:hover { background:#1e293b; color:#fff; }
.logout { margin-top:auto; background:#dc2626; color:#fff; text-align:center; padding:10px; border-radius:6px; text-decoration:none; }
.logout:hover { background:#b91c1c; }

/* Main Content Styling */
.main { flex:1; padding:30px 40px; }
.form-container {
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.form-container h2 {
    font-size: 24px;
    font-weight: 700;
    color: #1e3a8a;
    margin-bottom: 25px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    color: #334155;
    margin-bottom: 8px;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    transition: border-color 0.3s, box-shadow 0.3s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    outline: none;
}
.btn-submit {
    background:#2563eb; color:#fff; padding:12px 20px;
    border:none; border-radius:8px; cursor:pointer;
    font-size: 16px; font-weight: 600;
    transition: background 0.3s;
    width: 100%;
}
.btn-submit:hover { background:#1d4ed8; }

/* Messages */
.message-success { background:#dcfce7; color:#16a34a; padding:15px; border-radius:8px; margin-bottom:20px; font-weight: 600; }
.message-error { background:#fee2e2; color:#ef4444; padding:15px; border-radius:8px; margin-bottom:20px; font-weight: 600; }

/* Calculation Box */
#calculation-summary {
    background: #f1f5f9;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
    border-left: 5px solid #2563eb;
}
#calculation-summary p {
    margin-bottom: 8px;
    font-size: 15px;
    color: #334155;
}
#calculation-summary strong {
    color: #1e3a8a;
}
.total-payable {
    font-size: 20px !important;
    font-weight: 700;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px dashed #cbd5e1;
}
.rate-range-info {
    font-size: 12px;
    color: #64748b;
    margin-top: -10px;
    margin-bottom: 15px;
    text-align: right;
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-box">
        <img src="https://placehold.co/60x60/2563eb/ffffff?text=LOGO" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php">💼 Loans</a>
    <a href="my_payments.php">💰 Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php">📜 History</a>
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<main class="main">
    <div class="form-container">
        <h2>Request New Loan</h2>

        <?php if ($message): ?>
            <div class="message-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($has_active_loan): ?>
            <div class="message-error">
                You currently have an active loan with an outstanding balance of ₱<?= number_format($outstanding, 2) ?>. You cannot request a new loan until this is settled.
                <p style="margin-top: 10px; font-weight: 400;"><a href="my_loans.php" style="color: #dc2626; text-decoration: underline;">View your active loan here.</a></p>
            </div>
        <?php else: ?>
        <form method="POST" action="request_loan.php">
            <div class="form-group">
                <label for="amount">Loan Amount (Principal):</label>
                <input type="number" id="amount" name="amount" min="1000" step="500" required value="10000" oninput="calculateLoan()">
            </div>

            <div class="form-group">
                <label for="term_months">Loan Term (in Months):</label>
                <select id="term_months" name="term_months" required onchange="calculateLoan()">
                    <option value="" disabled selected>Select term</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($i == 6) ? 'selected' : '' ?>><?= $i ?> Months</option>
                    <?php endfor; ?>
                </select>
                <p class="rate-range-info">Rates range from 3.00% (1 month) to 5.00% (12 months).</p>
            </div>

            <div id="calculation-summary">
                <p>Calculated Interest Rate: <strong id="display-rate">--</strong></p>
                <p>Estimated Interest Amount: <strong id="display-interest">--</strong></p>
                <p class="total-payable">TOTAL PAYABLE: <strong id="display-total">--</strong></p>
            </div>

            <div class="form-group" style="margin-top: 30px;">
                <label for="purpose">Purpose of Loan:</label>
                <textarea id="purpose" name="purpose" rows="3" required></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Loan Request</button>
        </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Run initial calculation when the page loads
    if (!<?= json_encode($has_active_loan) ?>) {
        calculateLoan();
    }
});

/**
 * Sends an AJAX request to the backend to calculate the loan details (principal, interest, total).
 */
function calculateLoan() {
    const amount = document.getElementById('amount').value;
    const term = document.getElementById('term_months').value;

    const displayRate = document.getElementById('display-rate');
    const displayInterest = document.getElementById('display-interest');
    const displayTotal = document.getElementById('display-total');
    
    // Simple validation
    if (!amount || amount <= 0 || !term) {
        displayRate.textContent = '--';
        displayInterest.textContent = '--';
        displayTotal.textContent = '--';
        return;
    }

    // Prepare form data
    const formData = new FormData();
    formData.append('amount', amount);
    formData.append('term', term);

    // Use a loading state
    displayRate.textContent = 'Calculating...';
    displayInterest.textContent = 'Calculating...';
    displayTotal.textContent = 'Calculating...';

    // Fetch call to the backend API for calculation
    fetch('calculate_loan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            
            // 🔥 FIX: Defensive check to remove commas before parsing (if backend accidentally formatted the number)
            const rawInterest = String(data.interest_amount).replace(/,/g, '');
            const rawTotal = String(data.total_payable).replace(/,/g, '');
            
            // Parse the clean number strings
            const interestValue = parseFloat(rawInterest);
            const totalValue = parseFloat(rawTotal);

            // Format numbers using locale string for reliable currency display
            const formattedInterest = interestValue.toLocaleString('en-PH', { 
                style: 'currency', 
                currency: 'PHP',
                minimumFractionDigits: 2
            });
            
            const formattedTotal = totalValue.toLocaleString('en-PH', { 
                style: 'currency', 
                currency: 'PHP',
                minimumFractionDigits: 2
            });
            
            displayRate.textContent = parseFloat(data.interest_rate).toFixed(2) + '%';
            displayInterest.textContent = formattedInterest;
            displayTotal.textContent = formattedTotal;
            
        } else {
            console.error('Calculation Error:', data.error);
            displayRate.textContent = 'Error';
            displayInterest.textContent = 'Error';
            displayTotal.textContent = 'Error';
        }
    })
    .catch(error => {
        console.error('Network Error:', error);
        displayRate.textContent = 'Error';
        displayInterest.textContent = 'Error';
        displayTotal.textContent = 'Error';
    });
}
</script>
</body>
</html>
