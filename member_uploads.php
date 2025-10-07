<?php
require_once 'auth.php';
require_role(['staff', 'manager']);
require_once 'db.php';

$member_id = $_GET['member_id'] ?? null;
if (!$member_id) { header("Location: view_member_proofs.php"); exit; }

// Get member info
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch();
if (!$member) { die("Member not found."); }

// Fetch all uploaded proofs
$stmt = $pdo->prepare("
    SELECT * FROM member_photos
    WHERE member_id = ?
    ORDER BY submitted_date DESC
");
$stmt->execute([$member_id]);
$photos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($member['name']) ?> - Proof Uploads</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f8fafc;color:#1e293b;padding:30px;}
h1{font-size:24px;color:#1e3a8a;margin-bottom:10px;}
.back-btn{display:inline-block;margin-bottom:20px;background:#2563eb;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none;}
.back-btn:hover{background:#1d4ed8;}
.gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;}
.card{background:#fff;padding:10px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.card img{width:100%;border-radius:8px;cursor:pointer;transition:0.3s;}
.card img:hover{transform:scale(1.05);}
.card p{text-align:center;margin-top:8px;font-size:14px;color:#475569;}
</style>
</head>
<body>
<a href="upload_member_photo.php" class="back-btn">⬅ Back to Member List</a>
<h1>📁 Proof Uploads - <?= htmlspecialchars($member['name']) ?></h1>
<p>Phone: <?= htmlspecialchars($member['phone']) ?></p>
<hr style="margin:15px 0;border:0;border-top:1px solid #cbd5e1;">
<div class="gallery">
<?php if($photos): foreach($photos as $p): ?>
  <div class="card">
    <img src="uploads/<?= htmlspecialchars($p['filename']) ?>" alt="proof">
    <p>📅 <?= date('M d, Y', strtotime($p['submitted_date'])) ?></p>
  </div>
<?php endforeach; else: ?>
  <p>No uploads yet for this member.</p>
<?php endif; ?>
</div>
</body>
</html>
