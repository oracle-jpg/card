<?php
require_once 'auth.php';
require_once 'db.php';
// Tiyakin na mayroon kang payment_functions.php o ilipat ang notifyRole/logAudit functions dito.
// Assuming logAudit and notifyRole are globally available or defined in db.php/auth.php
// Kung wala pa, ide-define natin ang simple version ng calculateTotalPayable dito.

$user = current_user();

// Tiyakin na client lang ang may access
if ($user['role'] !== 'client') {
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

$member_id = null;
$msg = '';
$error = '';

// Kumuha ng Member ID
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([$user['id']]);
$member = $stmt->fetch();
if (!$member) {
    die("Error: Member record not found.");
}
$member_id = $member['id'];

// =========================================================
// ACCURATE OUTSTANDING BALANCE CALCULATION
// =========================================================
$outstanding = 0;
if ($member_id) {
    // 1. Fetch all *approved/ongoing* loans for the member
    $active_loans_stmt = $pdo->prepare("
        SELECT id, amount, interest_rate, term_months
        FROM loans 
        WHERE member_id = ? 
        AND status IN ('approved', 'ongoing', 'defaulted')
    ");
    $active_loans_stmt->execute([$member_id]);
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
        
        // Outstanding = Total Payable - Total Paid
        $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);
        $outstanding += $current_outstanding_for_loan;
    }
}
// =========================================================

// Kumuha ng Active/Ongoing Loans para sa dropdown
$loans = $pdo->prepare("
    SELECT id, amount, status 
    FROM loans 
    WHERE member_id = ? AND status IN ('approved', 'ongoing')
");
$loans->execute([$member_id]);
$active_loans = $loans->fetchAll();


// =========================================================
// PAYMENT SUBMISSION LOGIC
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $payment_method = $_POST['payment_method'];
    $payment_date = date('Y-m-d'); // Gamitin ang current date bilang payment date

    if (!$loan_id || $amount <= 0 || !in_array($payment_method, ['Cash', 'Gcash', 'Bank Transfer'])) {
        $error = "Invalid input. Please check your selected loan, amount, and payment method.";
    } else {
        // Tiyakin na ang loan ay ongoing o approved
        $loanCheck = $pdo->prepare("SELECT status FROM loans WHERE id = ? AND member_id = ? AND status IN ('approved', 'ongoing')");
        $loanCheck->execute([$loan_id, $member_id]);
        
        if (!$loanCheck->fetch()) {
            $error = "Selected loan is invalid or already fully paid.";
        } else {
            // TRANSACTION BEGIN
            $pdo->beginTransaction();
            try {
                // 1. Record the payment
                // NOTE: Ang status ay 'pending' by default, at i-ve-verify ng Staff.
                $stmt = $pdo->prepare("
                    INSERT INTO payments (loan_id, amount, payment_date, method, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$loan_id, $amount, $payment_date, $payment_method]);

                // 2. Kumuha ng ID ng bagong payment
                $payment_id = $pdo->lastInsertId();

                // 3. Send notification to staff/manager
                // notifyRole($pdo, 'staff', "New Client Payment (₱" . number_format($amount, 2) . ")", 
                //            "Client " . htmlspecialchars($user['full_name']) . " declared a payment of ₱" . number_format($amount, 2) . " for Loan ID: " . $loan_id . ".");
                
                // logAudit($pdo, $user['id'], "Declared payment of ₱" . number_format($amount, 2) . " for Loan ID: " . $loan_id . " via " . $payment_method);

                $pdo->commit();
                
                // *** UPDATED: Instead of redirecting, set a message and provide a link for proof upload. ***
                $msg = "Payment declaration successful! Staff will verify the payment soon (Payment ID: {$payment_id}). 
                        <a href='upload_photo.php?loan_id={$loan_id}&payment_id={$payment_id}' style='color: #047857; text-decoration: underline; font-weight: 700;'>CLICK HERE TO UPLOAD PROOF IMMEDIATELY.</a>";
                
                // Do NOT exit/redirect
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Payment</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { display:flex; background:#f8fafc; color:#1e293b; }
        .sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
        .logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; border-radius: 8px; }
        .logo-box img { height: 60px; width: auto; border-radius: 6px; }
        .sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
        .sidebar a:hover{background:#1e293b;color:#fff;}
        .logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
        .logout:hover{background:#b91c1c;}
        
        .main{flex:1;padding:30px 40px;}
        .card { max-width: 600px; margin: 20px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        h1 { color:#1e3a8a; margin-bottom: 25px; text-align: center;}
        
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 500; color: #334155; }
        input[type="number"], select { 
            width: 100%; padding: 12px; border: 1px solid #cbd5e1; 
            border-radius: 8px; font-size: 16px; transition: border-color 0.3s;
        }
        input:focus, select:focus { border-color: #2563eb; outline: none; }
        
        button { 
            width: 100%; margin-top: 25px; background:#10b981; border:none; color:white; 
            padding:12px 20px; border-radius:8px; cursor:pointer; font-size: 16px; font-weight: 600; 
        }
        button:hover { background:#059669; }
        
        .msg { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; line-height: 1.5; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #4ade80; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        
        .balance-info { text-align: center; margin-bottom: 20px; padding: 15px; border-radius: 8px; background: #e0f2fe; border: 1px solid #93c5fd;}
        .balance-info p { margin: 5px 0; font-size: 16px; color: #1e3a8a; }
        .balance-info .amount { font-size: 28px; font-weight: 700; color: #dc2626; /* Changed to red for balance */ }
        .secondary-btn { background: #64748b !important; }
        .secondary-btn:hover { background: #475569 !important; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo-box">
        <img src="https://placehold.co/60x60/2563eb/ffffff?text=LOGO" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="request_loan.php">💸 Request Loan</a>
    <a href="my_loans.php">💼 My Loans</a>
    <a href="my_payments.php">💰 My Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php">📜 My History</a>
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<!-- Main -->
<main class="main">
    <div class="card">
        <h1>💵 Record Payment</h1>
        
        <div class="balance-info">
            <p>Total Outstanding Balance (Principal + Interest):</p>
            <p class="amount">₱<?= number_format(max($outstanding, 0), 2) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($msg)): ?>
            <div class="msg success"><?= $msg // $msg contains HTML link, so don't escape ?></div>
        <?php endif; ?>

        <?php if (empty($active_loans)): ?>
            <div class="msg error">No active loans found to pay. Please request a loan first.</div>
        <?php else: ?>
            <form method="post">
                <label for="loan_id">Select Loan to Pay:</label>
                <select name="loan_id" id="loan_id" required>
                    <?php foreach ($active_loans as $loan): ?>
                        <option value="<?= $loan['id'] ?>">
                            Loan #<?= $loan['id'] ?> (Principal: ₱<?= number_format($loan['amount'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="amount">Payment Amount (₱):</label>
                <input type="number" step="0.01" min="1" name="amount" id="amount" required placeholder="e.g., 500.00">

                <label for="payment_method">Payment Method:</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="Cash">Cash (To Staff)</option>
                    <option value="Gcash">Gcash (Online)</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>

                <button type="submit">Declare Payment</button>
            </form>
        <?php endif; ?>
        
        <button onclick="window.location='my_payments.php'" class="secondary-btn">Go Back to Payments</button>
    </div>
</main>

</body>
</html>
