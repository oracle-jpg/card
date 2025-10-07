<?php
require_once 'auth.php';
require_role(['staff']);
require_once 'db.php';
require_once 'send_email.php';

$user = current_user();
$msg = "";

// ✅ Generate random password
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$!';
    return substr(str_shuffle($chars), 0, $length);
}

// ✅ Add new member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);

    if ($name && $phone) {
        $username = strtolower(explode(' ', $name)[0]) . rand(100,999);
        $temp_pass = generate_password(10);
        $hash_pass = password_hash($temp_pass, PASSWORD_BCRYPT);

        // create account
        $stmtUser = $pdo->prepare("
            INSERT INTO users (username, password_hash, role, full_name, email)
            VALUES (?, ?, 'client', ?, ?)
        ");
        $stmtUser->execute([$username, $hash_pass, $name, $email]);
        $user_id = $pdo->lastInsertId();

        // add member
        $stmtMem = $pdo->prepare("
            INSERT INTO members (user_id, name, address, phone, loan_amount, loan_start, created_by)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        $stmtMem->execute([$user_id, $name, $address, $phone, $loan_amount, $user['id']]);

        // send email if provided
        if (!empty($email)) {
            sendAccountEmail($email, $name, $username, $temp_pass);
        }

        // 🔔 send notifications
        notifyRole($pdo, 'admin', 'New Member Added', "$name was added by {$user['full_name']}.");
        notifyRole($pdo, 'manager', 'New Member Added', "$name was added by {$user['full_name']}.");
        sendNotification($pdo, $user['id'], 'Member Added', "You successfully added $name.");

        $msg = "✅ Member added successfully!<br>
                <b>Username:</b> $username<br>
                <b>Password:</b> $temp_pass<br>";
    } else {
        $msg = "⚠ Please fill in required fields.";
    }
}

// fetch all members
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name AS created_by_name 
    FROM members m 
    LEFT JOIN users u ON m.created_by = u.id
    ORDER BY m.created_at DESC
");
$stmt->execute();
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Members - Staff</title>
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
.card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:25px;}
form label{display:block;margin-top:12px;font-weight:500;}
form input{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-top:5px;}
button{background:#2563eb;border:none;color:white;padding:10px 20px;border-radius:6px;cursor:pointer;margin-top:15px;}
button:hover{background:#1d4ed8;}
.msg{margin-top:15px;padding:10px;border-radius:6px;background:#dcfce7;color:#166534;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;}
th{background:#f1f5f9;}
</style>
</head>
<body>
<aside class="sidebar">
  <h2>Staff Panel</h2>
  <a href="staff_dashboard.php">🏠 Home</a>
  <a href="record_payment.php">💰 Record Payment</a>
  <a href="members.php">👥 Manage Members</a>
  <a href="upload_member_photo.php">📸 Upload Proof</a>
  <a href="notifications.php">🔔 Notifications <span id="notifCount" style="background:#ef4444;color:white;padding:2px 6px;border-radius:10px;font-size:12px;margin-left:6px;">0</span></a>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<main class="main">
  <header>
    <h1>👥 Manage Members</h1>
    <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (Staff)</div>
  </header>

  <div class="card">
    <h2>Add New Member (Walk-in)</h2>
    <form method="POST">
      <label>Full Name</label>
      <input type="text" name="name" required placeholder="Enter full name">
      <label>Email (optional)</label>
      <input type="email" name="email" placeholder="Enter email address">
      <label>Phone Number</label>
      <input type="text" name="phone" required placeholder="09XXXXXXXXX">
      <label>Address</label>
      <input type="text" name="address" placeholder="Enter address">
      <label>Initial Loan Amount (₱)</label>
      <input type="number" step="0.01" name="loan_amount" placeholder="Enter amount">
      <button name="add_member">Add Member</button>
    </form>
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
  </div>

  <div class="card">
    <h2>📋 Member List</h2>
    <table>
      <tr><th>ID</th><th>Name</th><th>Phone</th><th>Loan (₱)</th><th>Status</th><th>Created By</th></tr>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= $m['id'] ?></td>
          <td><?= htmlspecialchars($m['name']) ?></td>
          <td><?= htmlspecialchars($m['phone']) ?></td>
          <td><?= number_format($m['loan_amount'], 2) ?></td>
          <td><?= htmlspecialchars($m['status']) ?></td>
          <td><?= htmlspecialchars($m['created_by_name'] ?? 'System') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>
</body>
</html>
