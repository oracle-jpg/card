<?php
// members.php
require_once 'auth.php';
require_role(['admin','manager','staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO members (name, address, phone, loan_amount, loan_start) VALUES (?, ?, ?, ?, CURDATE())");
    $stmt->execute([$name, $address, $phone, $loan_amount]);
    $msg = "Member added.";
}

$members = $pdo->query("SELECT * FROM members ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Members</title></head><body>
  <h2>Members</h2>
  <?php if(!empty($msg)) echo "<p>$msg</p>"; ?>
  <form method="post">
    <input name="name" placeholder="Full name" required>
    <input name="phone" placeholder="Phone">
    <input name="address" placeholder="Address">
    <input name="loan_amount" placeholder="Loan amount" type="number" step="0.01">
    <button name="add_member">Add Member</button>
  </form>

  <h3>List</h3>
  <table border="1" cellpadding="6">
    <tr><th>ID</th><th>Name</th><th>Phone</th><th>Loan</th><th>Status</th></tr>
    <?php foreach($members as $m): ?>
      <tr>
        <td><?=htmlspecialchars($m['id'])?></td>
        <td><?=htmlspecialchars($m['name'])?></td>
        <td><?=htmlspecialchars($m['phone'])?></td>
        <td><?=number_format($m['loan_amount'],2)?></td>
        <td><?=htmlspecialchars($m['status'])?></td>
      </tr>
    <?php endforeach; ?>    
  </table>
  <p><a href="dashboard.php">Back</a></p>
</body></html>
