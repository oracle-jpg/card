<?php
require_once 'auth.php';
require_once 'db.php';

// ✅ Get logged-in user
$user = current_user();

// Tiyakin na client lang ang may access
if ($user['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

// =========================================================
// LOAN CALCULATION UTILITY
// =========================================================
function calculateTotalPayable($principal, $rate, $term_months) {
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
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
// Outstanding Balance Calculation
// =========================================================
$outstanding = 0;
if ($member_id) {
    $active_loans_stmt = $pdo->prepare("
        SELECT id, amount, interest_rate, term_months
        FROM loans
        WHERE member_id = ?
        AND status IN ('approved', 'ongoing', 'defaulted')
    ");
    $active_loans_stmt->execute([$member_id]);
    $active_loans_data = $active_loans_stmt->fetchAll();

    foreach ($active_loans_data as $loan) {
        $total_payable = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);

        $total_paid_loan_stmt = $pdo->prepare("
            SELECT SUM(amount) AS loan_paid
            FROM payments
            WHERE loan_id = ? AND status = 'verified'
        ");
        $total_paid_loan_stmt->execute([$loan['id']]);
        $loan_paid = $total_paid_loan_stmt->fetchColumn() ?? 0;

        $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);
        $outstanding += $current_outstanding_for_loan;
    }
}

// =========================================================
// Fetch Active Loans for Dropdown (and now for single selection)
// =========================================================
$loans_query = $pdo->prepare("
    SELECT id, amount, status
    FROM loans
    WHERE member_id = ? AND status IN ('approved', 'ongoing')
");
$loans_query->execute([$member_id]);
$active_loans = $loans_query->fetchAll(); // This now holds all active loans

$num_active_loans = count($active_loans);

// =========================================================
// PAYMENT SUBMISSION LOGIC (Updated to match database columns and file upload)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $payment_method = $_POST['payment_method'];

    // NEW: Use sender_account_number to match the database column
    $sender_account_number = trim($_POST['sender_account_number'] ?? '');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_date = date('Y-m-d');
    $proof_of_payment_path = null; // Default value

    // 1. Validation for Required Fields
    if (!$loan_id || $amount <= 0 || !in_array($payment_method, ['Cash', 'Gcash'])) {
        $error = "Invalid input. Please check your selected loan, amount, and payment method.";
    } elseif ($payment_method === 'Gcash' && (empty($sender_account_number) || empty($reference_no))) {
        // Require GCash details for Gcash payments
        $error = "For GCash payments, the GCash Number and Reference Number are required.";
    } else {
        $loanCheck = $pdo->prepare("SELECT status FROM loans WHERE id = ? AND member_id = ? AND status IN ('approved', 'ongoing')");
        $loanCheck->execute([$loan_id, $member_id]);

        if (!$loanCheck->fetch()) {
            $error = "Selected loan is invalid or already fully paid.";
        } else {
            // 2. Handle File Upload (Proof of Payment)
            if ($payment_method === 'Gcash' && isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/'; // Siguraduhin na may folder na 'uploads'
                $file_name = uniqid('proof_') . '_' . basename($_FILES['proof_of_payment']['name']);
                $target_file = $upload_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Check file size, type, etc.
                if ($_FILES['proof_of_payment']['size'] > 5000000) { // 5MB limit
                    $error = "Sorry, your file is too large. Max 5MB.";
                } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'pdf'])) {
                    $error = "Sorry, only JPG, JPEG, PNG, & PDF files are allowed.";
                } elseif (move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $target_file)) {
                    $proof_of_payment_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }

            // 3. Insert Payment Record only if no error occurred during upload/validation
            if (empty($error)) {
                $pdo->beginTransaction();
                try {
                    // **CRITICAL FIX: Changed column names from 'gcash_number'/'reference_no' to 'sender_account_number'/'reference_number'**
                    $stmt = $pdo->prepare("
                        INSERT INTO payments
                            (loan_id, amount, payment_date, method, sender_account_number, reference_number, proof_of_payment_path, created_at, status)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                    ");

                    $stmt->execute([
                        $loan_id,
                        $amount,
                        $payment_date,
                        $payment_method,
                        $sender_account_number,
                        $reference_no,         // Pareho lang ang name sa DB: reference_number
                        $proof_of_payment_path
                    ]);

                    // Log action (assuming log_action function is defined in auth.php)
                    if (function_exists('log_action')) {
                        log_action($pdo, $user['id'], "Declared a payment of ₱" . number_format($amount, 2) . " via {$payment_method} for Loan #{$loan_id}.");
                    }

                    $pdo->commit();
                    $msg = "✅ Payment declaration successful! Staff will verify your payment soon.";

                    // Clear post data after successful submission
                    unset($_POST);

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage() . " - Check if all columns are present: sender_account_number, reference_number, proof_of_payment_path";
                    // Tiyakin na hindi mag-e-error kung mali ang column name
                }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base Styles */
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { display:flex; background:#f8fafc; color:#1e293b; }

        /* Sidebar */
        .sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
        .logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; border-radius: 8px; }
        .logo-box img { height: 60px; width: auto; border-radius: 6px; }
        .sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
        .sidebar a:hover{background:#1e293b;color:#fff;}
        .logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
        .logout:hover{background:#b91c1c;}

        /* Main Content */
        .main{flex:1;padding:30px 40px;}
        .card { max-width: 600px; margin: 20px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        h1 { color:#1e3a8a; margin-bottom: 25px; text-align: center;}

        /* Form Elements */
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 500; color: #334155; }
        input[type="number"], input[type="text"], input[type="file"], select {
            width: 100%; padding: 12px; border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 16px; transition: border-color 0.3s;
        }
        input:focus, select:focus { border-color: #2563eb; outline: none; }

        /* Buttons */
        button[type="submit"] {
            width: 100%; margin-top: 25px; background:#10b981; border:none; color:white;
            padding:12px 20px; border-radius:8px; cursor:pointer; font-size: 16px; font-weight: 600;
        }
        button[type="submit"]:hover { background:#059669; }
        .secondary-btn {
            background: #64748b !important;
            margin-top: 10px;
        }
        .secondary-btn:hover { background: #475569 !important; }

        /* Messages & Info */
        .msg { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; line-height: 1.5; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #4ade80; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .balance-info { text-align: center; margin-bottom: 20px; padding: 15px; border-radius: 8px; background: #e0f2fe; border: 1px solid #93c5fd;}
        .balance-info p { margin: 5px 0; font-size: 16px; color: #1e3a8a; }
        .balance-info .amount { font-size: 28px; font-weight: 700; color: #dc2626; }
        .single-loan-display {
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
            color: #334155;
            font-size: 16px;
            margin-top: 5px;
            margin-bottom: 15px;
        }


        /* Responsive */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; padding: 15px 20px; }
            .main { padding: 20px; }
            .card { margin: 0 auto; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <a href="client_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="request_loan.php"><i class="fas fa-hand-holding-usd"></i> Request Loan</a>
    <a href="my_loans.php"><i class="fas fa-briefcase"></i> My Loans</a>
    <a href="submit_payment.php" style="background:#1e293b;color:#fff;"><i class="fas fa-money-bill-wave"></i> Submit Payment</a>
    <a href="my_payments.php"><i class="fas fa-wallet"></i> My Payments</a>
    <a href="my_history.php"><i class="fas fa-history"></i> My History</a>
    
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
            <div class="msg success"><?= $msg ?></div>
        <?php endif; ?>

        <?php if ($num_active_loans === 0): ?>
            <div class="msg error">No active loans found to pay. Please request a loan first.</div>
        <?php else: ?>
            <!-- UPDATED: Added enctype="multipart/form-data" for file upload -->
            <form method="post" enctype="multipart/form-data">
                <label for="loan_id">Loan to Pay:</label>
                <?php if ($num_active_loans === 1):
                    $single_loan = $active_loans[0]; ?>
                    <div class="single-loan-display">
                        Principal: ₱<?= number_format($single_loan['amount'], 2) ?>
                    </div>
                    <input type="hidden" name="loan_id" value="<?= $single_loan['id'] ?>">
                <?php else: // Multiple loans, use dropdown ?>
                    <select name="loan_id" id="loan_id" required>
                        <?php foreach ($active_loans as $loan): ?>
                            <option value="<?= $loan['id'] ?>">
                                Principal: ₱<?= number_format($loan['amount'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label for="amount">Payment Amount (₱):</label>
                <input type="number" step="0.01" min="1" name="amount" id="amount" required placeholder="e.g., 500.00">

                <label for="payment_method">Payment Method:</label>
                <select name="payment_method" id="payment_method" required onchange="toggleGCashFields()">
                    <option value="Cash">Cash (To Staff)</option>
                    <option value="Gcash">Gcash (Online)</option>
                </select>

                <div id="gcashFields" style="display:none;">
                    <!-- UPDATED: Renamed input name to sender_account_number to match DB column -->
                    <label for="sender_account_number">GCash Number (Sender's Account):</label>
                    <input type="text" name="sender_account_number" id="sender_account_number" placeholder="e.g., 09XXXXXXXXX">

                    <label for="reference_no">Reference Number:</label>
                    <input type="text" name="reference_no" id="reference_no" placeholder="Enter GCash Reference Number">

                    <!-- NEW FIELD: Proof of Payment Upload -->
                    <label for="proof_of_payment">Proof of Payment (Image/PDF):</label>
                    <input type="file" name="proof_of_payment" id="proof_of_payment" accept=".jpg, .jpeg, .png, .pdf">
                </div>

                <button type="submit">Declare Payment</button>
            </form>
        <?php endif; ?>

        <button onclick="window.location='client_dashboard.php'" class="secondary-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</button>
    </div>
</main>

<script>
function toggleGCashFields() {
    const method = document.getElementById('payment_method').value;
    const gcashFields = document.getElementById('gcashFields');
    const senderAcctInput = document.getElementById('sender_account_number');
    const referenceNoInput = document.getElementById('reference_no');
    const proofInput = document.getElementById('proof_of_payment');

    if (method === 'Gcash') {
        gcashFields.style.display = 'block';
        // Set required for GCash fields for front-end validation
        senderAcctInput.required = true;
        referenceNoInput.required = true;
        proofInput.required = true;
    } else {
        gcashFields.style.display = 'none';
        // Remove required attribute for Cash payments
        senderAcctInput.required = false;
        referenceNoInput.required = false;
        proofInput.required = false;
    }
}

// Trigger once on load to handle selected option if user hits back button
document.addEventListener('DOMContentLoaded', toggleGCashFields);
</script>

</body>
</html>