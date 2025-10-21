<?php
require_once 'auth.php';
require_role(['staff', 'manager']); // Ensure only authorized roles can view this
require_once 'db.php';

$member_id = $_GET['member_id'] ?? null;
if (!$member_id) {
    // Redirect if no member_id is provided, assuming a list of members is available
    header("Location: view_members.php");
    exit;
}

// Get member info
$stmt = $pdo->prepare("SELECT id, name, phone FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch();
if (!$member) {
    die("Member not found.");
}

// Fetch all uploaded proofs for this member, ordered by date (most recent first)
$stmt = $pdo->prepare("
    SELECT * FROM member_photos
    WHERE member_id = ?
    ORDER BY submitted_date DESC
");
$stmt->execute([$member_id]);
$photos = $stmt->fetchAll();

// Group photos by month and year for chronological display
$photos_by_month = [];
foreach ($photos as $photo) {
    $month_year = date('F Y', strtotime($photo['submitted_date'])); // e.g., "March 2024"
    if (!isset($photos_by_month[$month_year])) {
        $photos_by_month[$month_year] = [];
    }
    $photos_by_month[$month_year][] = $photo;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($member['name']) ?> - Business Progress Monitoring</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Basic Reset & Font */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{background:#f8fafc;color:#1e293b;padding:30px;}

/* Page Layout Container */
.page-container{display:flex;gap:30px;max-width:1300px;margin:0 auto;}

/* Sidebar for Member Info */
.sidebar{flex:0 0 320px;background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.08);height:fit-content;}
.sidebar h2{font-size:24px;color:#1e3a8a;margin-bottom:15px;}
.sidebar p{font-size:15px;margin-bottom:8px;line-height:1.5;}
.sidebar p strong{color:#334155;font-weight:600;}
.sidebar .detail-item{margin-bottom:12px;}

/* Back Button */
.back-btn{display:inline-flex;align-items:center;gap:8px;margin-bottom:25px;background:#475569;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:500;transition:background 0.3s ease;}
.back-btn:hover{background:#334155;}
.back-btn svg{width:18px;height:18px;}

/* Main Content Area (Gallery) */
.content{flex:1;}
.content h1{font-size:32px;color:#1e3a8a;margin-bottom:25px;border-bottom:1px solid #e2e8f0;padding-bottom:15px;}
.content h1 span{font-size:24px;color:#475569;font-weight:400;display:block;margin-top:5px;}

/* Month Section Styling */
.month-section{margin-bottom:40px;}
.month-section h2{font-size:24px;color:#334155;margin-bottom:20px;position:relative;padding-left:15px;}
.month-section h2::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:6px;height:24px;background:#2563eb;border-radius:3px;}

/* Image Gallery Styling */
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.06);transition:transform 0.2s ease, box-shadow 0.2s ease;overflow:hidden;}
.card:hover{transform:translateY(-5px);box-shadow:0 8px 18px rgba(0,0,0,0.12);}

.card-image-wrapper{width:100%;height:180px; /* Fixed height for image area */
                    display:flex;align-items:center;justify-content:center;
                    background:#f0f4f8;border-bottom:1px solid #e2e8f0;}
.card img{max-width:95%;max-height:95%; /* Scale image within wrapper */
          object-fit:contain;display:block;margin:0;border-radius:6px;cursor:pointer;}

.card-details{padding:15px;}
.card-details p{text-align:center;font-size:14px;color:#64748b;font-weight:500;}

.no-uploads{text-align:center;color:#64748b;font-size:18px;padding:50px;background:#f0f4f8;border-radius:12px;box-shadow:inset 0 1px 3px rgba(0,0,0,0.05);}
</style>
</head>
<body>
<div class="page-container">
    <div class="sidebar">
        <a href="upload_member_photo.php" class="back-btn"> <!-- Link to your member list page -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
            Back to Member List
        </a>
        <h2>Client Details</h2>
        <div class="detail-item">
            <p><strong>Name:</strong> <span><?= htmlspecialchars($member['name']) ?></span></p>
        </div>
        <div class="detail-item">
            <p><strong>Phone:</strong> <span><?= htmlspecialchars($member['phone']) ?></span></p>
        </div>
        <!-- Add more client details if needed -->

        <!-- REMOVED: "Upload New Proof" button as staff only monitors here -->
    </div>

    <div class="content">
        <h1>Business Progress Monitoring
            <span>Review monthly proofs to track client business changes</span>
        </h1>

        <?php if (!empty($photos_by_month)): ?>
            <?php foreach ($photos_by_month as $month_year => $monthly_photos): ?>
                <div class="month-section">
                    <h2><?= htmlspecialchars($month_year) ?></h2>
                    <div class="gallery">
                        <?php foreach ($monthly_photos as $p): ?>
                            <div class="card">
                                <div class="card-image-wrapper">
                                    <!-- Assume staff might click to view a larger version -->
                                    <img src="uploads/<?= htmlspecialchars($p['filename']) ?>" alt="Business proof for <?= htmlspecialchars($member['name']) ?> on <?= date('M d, Y', strtotime($p['submitted_date'])) ?>">
                                </div>
                                <div class="card-details">
                                    <p>Uploaded: <?= date('M d, Y', strtotime($p['submitted_date'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-uploads">
                <p>No business progress proofs have been uploaded for <?= htmlspecialchars($member['name']) ?> yet.</p>
                <p>Please inform the client to submit their monthly proofs.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>