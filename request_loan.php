<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get member info
$stmt = $pdo->prepare("SELECT id, ci_status FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_id = $member['id'] ?? null;
$ci_status = $member['ci_status'] ?? 'pending';

$msg = '';
$error = '';

function calculateTotalPayable($principal, $rate, $term_months) {
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return round($principal + $interest_amount, 2);
}

// --- CI VALIDATION ---
if ($ci_status !== 'approved') {
    $error = "⚠️ You cannot request a loan yet. Your Credit Investigation status is currently 
              <b>" . ucfirst($ci_status) . "</b>. Please wait for approval before applying for a loan.";
}

// --- CHECK ACTIVE LOANS ---
$has_active_loan = false;
$outstanding = 0;
if ($member_id) {
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE member_id = ? AND status IN ('approved','ongoing','defaulted')");
    $stmt->execute([$member_id]);
    $loans = $stmt->fetchAll();

    foreach ($loans as $loan) {
        $total = calculateTotalPayable($loan['amount'], $loan['interest_rate'], $loan['term_months']);
        $stmt2 = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE loan_id = ? AND status = 'verified'");
        $stmt2->execute([$loan['id']]);
        $paid = $stmt2->fetchColumn() ?? 0;
        $balance = $total - $paid;
        if ($balance > 0) {
            $has_active_loan = true;
            $outstanding += $balance;
        }
    }
}

// --- FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if ($has_active_loan) {
        $error = "⚠️ You still have an active loan with an outstanding balance of ₱" . number_format($outstanding, 2) . ". Please settle it first.";
    } else {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $term_months = filter_input(INPUT_POST, 'term_months', FILTER_VALIDATE_INT);
        $purpose = trim($_POST['purpose']);

        if ($amount > 0 && $term_months > 0 && $purpose) {
            $MIN_RATE = 3.0;
            $MAX_RATE = 5.0;
            $ratio = ($term_months - 1) / 11;
            $final_rate = round($MIN_RATE + (($MAX_RATE - $MIN_RATE) * $ratio), 2);
            $total_payable = calculateTotalPayable($amount, $final_rate, $term_months);

            $stmt = $pdo->prepare("INSERT INTO loans (member_id, amount, interest_rate, term_months, purpose, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$member_id, $amount, $final_rate, $term_months, $purpose]);
            $loan_id = $pdo->lastInsertId();

            logAudit($pdo, $user_id, 'LOAN_REQUESTED', "Requested loan ID {$loan_id}: ₱{$amount} at {$final_rate}% for {$term_months} months.");
            notifyRole($pdo, 'admin', 'New Loan Request', "Member {$user['full_name']} requested a loan of ₱" . number_format($amount, 2) . ".");

            $msg = "✅ Your loan request (<b>₱" . number_format($amount, 2) . "</b> for <b>{$term_months} months</b>) has been submitted for review.<br>
                    Total expected payable: <b>₱" . number_format($total_payable, 2) . "</b>.";
        } else {
            $error = "⚠️ Please fill in all fields correctly.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request New Loan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { display:flex; background:#f8fafc; color:#1e293b; margin:0; font-family:'Inter',sans-serif; }
.sidebar { width:230px; background:#0f172a; color:#fff; min-height:100vh; padding:25px 20px; display:flex; flex-direction:column; }
.sidebar a { color:#e2e8f0; text-decoration:none; padding:10px; margin-bottom:8px; border-radius:6px; display:block; transition:0.3s; }
.sidebar a:hover, .sidebar a.active { background:#1e293b; }
.main { flex:1; padding:40px; }
.container { max-width:650px; margin:auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { color:#1e3a8a; margin-bottom:20px; text-align:center; }
.message { padding:15px; border-radius:6px; margin-bottom:20px; font-weight:500; }
.success { background:#dcfce7; border:1px solid #4ade80; color:#166534; }
.error { background:#fee2e2; border:1px solid #f87171; color:#991b1b; }
label { font-weight:600; display:block; margin-bottom:5px; }
input, select, textarea { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:15px; }
button { background:#2563eb; color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:600; width:100%; }
button:hover { background:#1d4ed8; }
.rate-box { background:#f1f5f9; padding:15px; border-left:4px solid #2563eb; border-radius:8px; margin-bottom:15px; }
.rate-box strong { color:#1e3a8a; }
</style>
</head>
<body>

<aside class="sidebar">
  <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" style="height:60px; margin-bottom:30px;">
  <a href="client_dashboard.php">🏠 Home</a>
  <a href="my_loans.php" class="active">💼 Loans</a>
  <a href="my_payments.php">💰 Payments</a>
  <a href="upload_photo.php">📸 Upload Proof</a>
  <a href="my_history.php">📜 History</a>
</aside>

<main class="main">
  <div class="container">
    <h2>Request New Loan</h2>

    <?php if ($msg): ?>
      <div class="message success"><?= $msg ?></div>
    <?php elseif ($error): ?>
      <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!$msg && empty($error)): ?>
    <form method="POST">
      <label for="amount">Loan Amount (₱):</label>
      <input type="number" name="amount" id="amount" min="1000" step="500" value="10000" required>

      <label for="term_months">Loan Term (Months):</label>
      <select name="term_months" id="term_months" required onchange="calculateLoan()">
        <?php for ($i=1; $i<=12; $i++): ?>
          <option value="<?= $i ?>" <?= $i==6?'selected':'' ?>><?= $i ?> Month<?= $i>1?'s':'' ?></option>
        <?php endfor; ?>
      </select>
      <p style="font-size:13px;color:#64748b;margin-top:-10px;">Rates range from 3.0% (1 month) to 5.0% (12 months).</p>

      <div class="rate-box" id="calcBox">
        <p>Calculated Interest Rate: <strong id="rateText">--%</strong></p>
        <p>Estimated Interest: <strong id="interestText">--</strong></p>
        <p><strong>TOTAL PAYABLE: <span id="totalText">--</span></strong></p>
      </div>

      <label for="purpose">Purpose of Loan:</label>
      <textarea name="purpose" id="purpose" rows="3" required></textarea>

      <button type="submit">Submit Loan Request</button>
    </form>
    <?php endif; ?>
  </div>
</main>

<script>
function calculateLoan() {
  const amount = parseFloat(document.getElementById('amount').value || 0);
  const term = parseInt(document.getElementById('term_months').value || 0);
  if (!amount || !term) return;

  const minRate = 3.0, maxRate = 5.0;
  const ratio = (term - 1) / 11;
  const rate = (minRate + (maxRate - minRate) * ratio).toFixed(2);
  const interest = (amount * (rate/100) * (term/12)).toFixed(2);
  const total = (parseFloat(amount) + parseFloat(interest)).toFixed(2);

  document.getElementById('rateText').innerText = rate + '%';
  document.getElementById('interestText').innerText = '₱' + parseFloat(interest).toLocaleString();
  document.getElementById('totalText').innerText = '₱' + parseFloat(total).toLocaleString();
}
document.addEventListener('DOMContentLoaded', calculateLoan);
</script>

</body>
</html>
