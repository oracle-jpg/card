<?php
// reports.php
require_once 'auth.php';
require_role(['admin','manager','staff']);

$totalLoans = $pdo->query("SELECT SUM(amount) AS total FROM loans")->fetchColumn();
$totalCollected = $pdo->query("SELECT SUM(amount) AS total FROM payments")->fetchColumn();
$membersCount = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();

$recentPayments = $pdo->query("SELECT p.*, m.name AS member_name, u.full_name AS collector FROM payments p JOIN loans l ON p.loan_id = l.id JOIN members m ON l.member_id = m.id LEFT JOIN users u ON p.collected_by = u.id ORDER BY p.created_at DESC LIMIT 20")->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Reports</title></head><body>
  <h2>Reports & Analytics</h2>
  <ul>
    <li>Total loans disbursed: <?=number_format($totalLoans ?? 0,2)?></li>
    <li>Total collected: <?=number_format($totalCollected ?? 0,2)?></li>
    <li>Registered members: <?=htmlspecialchars($membersCount)?></li>
  </ul>

  <h3>Recent Payments</h3>
  <table border="1" cellpadding="6">
    <tr><th>ID</th><th>Member</th><th>Amount</th><th>Date</th><th>Collector</th></tr>
    <?php foreach($recentPayments as $p): ?>
      <tr>
        <td><?=$p['id']?></td>
        <td><?=htmlspecialchars($p['member_name'])?></td>
        <td><?=number_format($p['amount'],2)?></td>
        <td><?=htmlspecialchars($p['payment_date'])?></td>
        <td><?=htmlspecialchars($p['collector'])?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p><a href="dashboard.php">Back</a></p>
</body></html>
