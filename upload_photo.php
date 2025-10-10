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

// Get linked member record — auto-create if missing
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();

if (!$member) {
    $stmt = $pdo->prepare("INSERT INTO members (user_id, name, status, created_at) VALUES (?, ?, 'active', NOW())");
    $stmt->execute([$user_id, $user['full_name']]);

    // Re-fetch
    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
}

$member_id = $member['id'];

// Handle file upload
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];

    if ($file['error'] === 0) {
        $filename = time() . '_' . basename($file['name']);
        $targetDir = 'uploads/';
        $targetFile = $targetDir . $filename;

        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        move_uploaded_file($file['tmp_name'], $targetFile);

        $stmt = $pdo->prepare("INSERT INTO member_photos (member_id, filename, submitted_date) VALUES (?, ?, NOW())");
        $stmt->execute([$member_id, $filename]);

        $message = "✅ Proof uploaded successfully!";
    } else {
        $message = "❌ Failed to upload file.";
    }
}

// Fetch latest proof
$stmt = $pdo->prepare("SELECT * FROM member_photos WHERE member_id = ? ORDER BY submitted_date DESC LIMIT 1");
$stmt->execute([$member_id]);
$latest_proof = $stmt->fetch();
// 🔔 Notify staff & manager
notifyRole($pdo, 'staff', 'New Proof Uploaded', "{$user['full_name']} uploaded a monthly proof.");
notifyRole($pdo, 'manager', 'Client Proof Uploaded', "{$user['full_name']} uploaded proof.");
sendNotification($pdo, $user['id'], 'Proof Uploaded', 'Your proof has been received.');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Proof</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
body { display:flex; background:#f8fafc; color:#1e293b; }

/* Sidebar */
.sidebar {
  width:230px; background:#0f172a; color:#fff; min-height:100vh;
  padding:25px 20px; display:flex; flex-direction:column;
}
.logo-box {
    display: flex;
    justify-content: left; /* I-center ang image */
    align-items: center;
    padding: 15px 0;
    margin-bottom: 30px;
    border-radius: 8px;
}
.logo-box img {
    height: 60px; /* Fixed height for the logo */
    width: auto;
    border-radius: 6px; 
    }
.sidebar a {
  color:#e2e8f0; text-decoration:none; padding:10px;
  margin-bottom:8px; border-radius:6px; display:block; transition:0.3s;
}
.sidebar a:hover { background:#1e293b; color:#fff; }
.logout {
  margin-top:auto; background:#dc2626; color:#fff;
  text-align:center; padding:10px; border-radius:6px;
  text-decoration:none;
}
.logout:hover { background:#b91c1c; }

/* Main */
.main { flex:1; padding:30px 40px; }
header {
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:25px;
}
header h1 { font-size:24px; font-weight:600; color:#1e3a8a; }
.profile {
  background:#e0f2fe; color:#1e3a8a;
  padding:8px 15px; border-radius:8px; font-weight:500;
}

/* Upload box */
.upload-card {
  background:#fff; padding:30px; border-radius:10px;
  box-shadow:0 3px 8px rgba(0,0,0,0.08); text-align:center;
  max-width:600px; margin:auto;
}
.upload-card h2 { color:#1e3a8a; margin-bottom:15px; }
.upload-card p { color:#475569; margin-bottom:15px; }
.upload-card input[type=file] {
  padding:10px; border:1px solid #cbd5e1; border-radius:6px; width:100%;
}
.upload-card button {
  margin-top:15px; background:#2563eb; border:none;
  color:#fff; padding:10px 18px; border-radius:6px; cursor:pointer;
}
.upload-card button:hover { background:#1d4ed8; }

.message {
  margin-bottom:15px; padding:10px; border-radius:6px;
  font-weight:500;
}
.success { background:#d1fae5; color:#065f46; }
.error { background:#fee2e2; color:#991b1b; }

.latest-proof {
  margin-top:20px; text-align:left;
  border-top:1px solid #e2e8f0; padding-top:10px;
}
.latest-proof img {
  margin-top:10px; border-radius:8px;
  width:100%; max-width:300px;
}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
<div class="logo-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
  <a href="client_dashboard.php">🏠 Home</a>
  <a href="my_loans.php">💼 Loans</a>
  <a href="my_payments.php">💰 Payments</a>
  <a href="upload_photo.php">📸 Upload Proof</a>
  <a href="my_history.php">📜 History</a>
  <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<!-- Main -->
<main class="main">
  <header>
    <h1>Upload Monthly Proof</h1>
    <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
  </header>

  <div class="upload-card">
    <?php if ($message): ?>
      <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <h2>Submit Your Proof</h2>
    <p>Please upload a clear photo or screenshot as proof of your monthly payment.</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="photo" accept="image/*" required>
      <button type="submit">Upload Proof</button>
    </form>

    <?php if ($latest_proof): ?>
      <div class="latest-proof">
        <h3>Last Uploaded:</h3>
        <p><?= htmlspecialchars($latest_proof['filename']) ?> (<?= date('M d, Y', strtotime($latest_proof['submitted_date'])) ?>)</p>
        <img src="uploads/<?= htmlspecialchars($latest_proof['filename']) ?>" alt="Latest proof">
      </div>
      
    <?php endif; ?>
  </div>
</main>
</body>
</html>
