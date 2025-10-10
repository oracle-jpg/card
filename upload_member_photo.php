<?php
require_once 'auth.php';
require_role(['staff', 'manager']);
require_once 'db.php';

$user = current_user();
$search = $_GET['search'] ?? '';

// 🔹 Search members who have uploaded proofs
$query = "
    SELECT DISTINCT m.id, m.name, m.phone, COUNT(mp.id) AS total_uploads
    FROM members m
    LEFT JOIN member_photos mp ON mp.member_id = m.id
    WHERE m.name LIKE ? OR m.phone LIKE ?
    GROUP BY m.id
    ORDER BY m.name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", "%$search%"]);
$members = $stmt->fetchAll();
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
.table-container{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);}
.search-bar{display:flex;gap:10px;margin-bottom:20px;}
.search-bar input{flex:1;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;}
.search-bar button{background:#2563eb;color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;}
.search-bar button:hover{background:#1d4ed8;}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;}
th{background:#f1f5f9;color:#475569;}
.btn{background:#2563eb;color:#fff;padding:6px 10px;border-radius:6px;text-decoration:none;}
.btn:hover{background:#1d4ed8;}
</style>
</head>
<body>
<aside class="sidebar">
  <h2>Staff Panel</h2>
  <a href="staff_dashboard.php">🏠 Home</a>
  <a href="record_payment.php">💰 Record Payment</a>
  <a href="members.php">👥 Manage Members</a>
  <a href="upload_member_photo.php">📸 View Proofs</a>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<main class="main">
  <header>
    <h1>📸 Member Proof Uploads</h1>
    <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</div>
  </header>

  <div class="table-container">
    <form class="search-bar" method="GET">
      <input type="text" name="search" placeholder="Search member name or phone" value="<?= htmlspecialchars($search) ?>">
      <button type="submit">🔍 Search</button>
    </form>

    <table>
      <tr><th>#</th><th>Name</th><th>Phone</th><th>Total Uploads</th><th>Action</th></tr>
      <?php if($members): $i=1; foreach($members as $m): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($m['name']) ?></td>
          <td><?= htmlspecialchars($m['phone']) ?></td>
          <td><?= $m['total_uploads'] ?></td>
          <td><a href="member_uploads.php?member_id=<?= $m['id'] ?>" class="btn">👁 View Uploads</a></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5">No members found.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</main>
</body>
</html>
