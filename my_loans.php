<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$user_id = $_SESSION['user_id'];

// Get member linked to this user
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_id = $member['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM loans WHERE member_id = ?");
$stmt->execute([$member_id]);
$loans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><title>My Loans</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<h2>My Loans</h2>
<table class="table table-bordered">
  <thead><tr><th>ID</th><th>Amount</th><th>Interest</th><th>Term</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
    <?php foreach($loans as $l): ?>
      <tr>
        <td><?= $l['id'] ?></td>
        <td>₱<?= number_format($l['amount'],2) ?></td>
        <td><?= $l['interest_rate'] ?>%</td>
        <td><?= $l['term_months'] ?> mos</td>
        <td><?= ucfirst($l['status']) ?></td>
        <td><?= $l['disbursed_date'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p><a href="client_dashboard.php" class="btn btn-secondary">⬅ Back</a></p>
</body>
</html>
