<?php
// record_payment.php
require_once 'auth.php';
require_role(['admin','manager','staff']);

$loans = $pdo->query("SELECT l.id, l.amount, l.status, m.name AS member_name FROM loans l JOIN members m ON l.member_id = m.id WHERE l.status='ongoing'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = intval($_POST['loan_id']);
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount, payment_date, collected_by, method) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$loan_id, $amount, $payment_date, $_SESSION['user_id'], $method]);

    // simple log
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'payment_recorded', ?)")->execute([$_SESSION['user_id'], "loan:$loan_id amount:$amount"]);
    $msg = "Payment recorded.";
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Record Payment</title></head><body>
  <h2>Record Payment</h2>
  <?php if(!empty($msg)) echo "<p>$msg</p>"; ?>
  <form method="post">
    <label>Loan<br>
      <select name="loan_id" required>
        <?php foreach($loans as $l): ?>
          <option value="<?=$l['id']?>">#<?=$l['id']?> - <?=htmlspecialchars($l['member_name'])?> (<?=number_format($l['amount'],2)?>)</option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <label>Amount<br><input name="amount" type="number" step="0.01" required></label><br>
    <label>Method<br>
      <select name="method">
        <option>cash</option><option>gcash</option><option>bank_transfer</option>
      </select>
    </label><br>
    <label>Date<br><input type="date" name="payment_date" value="<?=date('Y-m-d')?>"></label><br><br>
    <button>Save Payment</button>
  </form>
  <p><a href="dashboard.php">Back</a></p>
</body></html>
