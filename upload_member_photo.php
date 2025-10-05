<?php
require_once 'auth.php';
require_role(['staff', 'manager']); // staff & manager can view
require_once 'db.php';

$user = current_user();

// Fetch all uploaded proofs
$stmt = $pdo->query("
    SELECT mp.id, mp.filename, mp.caption, mp.submitted_date, m.name AS member_name
    FROM member_photos mp
    JOIN members m ON mp.member_id = m.id
    ORDER BY mp.submitted_date DESC
");
$proofs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Member Proofs</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{display:flex;background:#f8fafc;color:#1e293b;}
.sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
.sidebar h2{font-size:20px;margin-bottom:30px;}
.sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
.sidebar a:hover{background:#1e293b;color:#fff;}
.logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
.logout:hover{background:#b91c1c;}
.main{flex:1;padding:30px 40px;}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
header h1{font-size:24px;font-weight:600;color:#1e3a8a;}
.profile{background:#e0f2fe;color:#1e3a8a;padding:8px 15px;border-radius:8px;font-weight:500;}
.card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;}
td a.view{color:#2563eb;text-decoration:none;font-weight:500;}
td a.view:hover{text-decoration:underline;}
</style>
</head>
<body>
<aside class="sidebar">
  <h2>Staff Panel</h2>
  <a href="staff_dashboard.php">🏠 Dashboard</a>
  <a href="record_payment.php">💰 Record Payment</a>
  <a href="members.php">👥 Manage Members</a>
  <a href="staff_view_proofs.php">📸 View Proofs</a>
  <a href="notifications.php">🔔 Notifications</a>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<main class="main">
  <header>
    <h1>📸 Uploaded Proofs</h1>
    <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (Staff)</div>
  </header>

  <div class="card">
    <h2>Member Uploaded Proofs</h2>
    <table>
      <tr>
        <th>ID</th>
        <th>Member Name</th>
        <th>Caption</th>
        <th>Date Submitted</th>
        <th>Action</th>
      </tr>
      <?php if ($proofs): ?>
        <?php foreach ($proofs as $p): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><?= htmlspecialchars($p['member_name']) ?></td>
            <td><?= htmlspecialchars($p['caption']) ?></td>
            <td><?= htmlspecialchars($p['submitted_date']) ?></td>
            <td><a class="view" href="upload/<?= htmlspecialchars($p['filename']) ?>" target="_blank">View / Download</a></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="5">No proofs uploaded yet.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</main>
</body>
</html>
