<?php
session_start();
require 'db.php';
require 'auth.php'; // Assuming auth.php contains log_action function

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { 
    session_destroy();
    header("Location: index.php");
    exit;
}

// Get linked member record — auto-create if missing (redundant check, but safe)
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    // If for some reason member record is missing, create it
    $stmt = $pdo->prepare("INSERT INTO members (user_id, name, status, created_at) VALUES (?, ?, 'active', NOW())");
    $stmt->execute([$user_id, $user['full_name']]);
    $member_id = $pdo->lastInsertId(); // Get the ID of the new member
} else {
    $member_id = $member['id'];
}

$message = ''; // Message to display to the user

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];

    // Basic file upload error checking
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = "❌ File is too large.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "❌ No file was uploaded.";
                break;
            default:
                $message = "❌ An unknown upload error occurred: " . $file['error'];
        }
    } else {
        $filename = uniqid('proof_') . '_' . basename($file['name']); // Use uniqid for better uniqueness
        $targetDir = 'uploads/';
        $targetFile = $targetDir . $filename;

        // Create uploads directory if it doesn't exist
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Ensure directory is writable
        }
        
        // Validate file type strictly
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $message = "❌ Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // Max 5MB
             $message = "❌ File is too large. Maximum size is 5MB.";
        } elseif (move_uploaded_file($file['tmp_name'], $targetFile)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO member_photos (member_id, filename, submitted_date) VALUES (?, ?, NOW())");
                $stmt->execute([$member_id, $filename]);
                
                log_action($pdo, $user['id'], 'Uploaded a new business proof.');
                $message = "✅ Proof uploaded successfully!";
                
            } catch (PDOException $e) {
                // Log database error and inform user
                error_log("Database error during proof upload: " . $e->getMessage());
                // Optionally remove the file if DB insert failed but file moved
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                }
                $message = "❌ A database error prevented your proof from being recorded. Please try again.";
            }
        } else {
            $message = "❌ Failed to save file on the server. Check folder permissions.";
        }
    }
}

// === Fetch Proof History ===
$proof_history = [];
$latest_proof_date_obj = null;
$proof_due_day = 15; // Assuming the due day is the 15th

if ($member_id) { 
    $stmt = $pdo->prepare("
        SELECT * FROM member_photos 
        WHERE member_id = ? 
        ORDER BY submitted_date DESC 
        LIMIT 1 
    "); 
    $stmt->execute([$member_id]);
    $latest_proof_record = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kukunin ang huling 10 records para sa history display
    $stmt = $pdo->prepare("
        SELECT * FROM member_photos 
        WHERE member_id = ? 
        ORDER BY submitted_date DESC 
        LIMIT 10 
    "); 
    $stmt->execute([$member_id]);
    $proof_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($latest_proof_record && isset($latest_proof_record['submitted_date'])) {
        $latest_proof_date_obj = new DateTime($latest_proof_record['submitted_date']);
    }
}

// =========================================================
// LOGIC FOR CALCULATING THE REQUIRED SUBMISSION MONTH
// This logic ensures the displayed month is the one the user needs to submit.
// =========================================================
$current_date = new DateTime();
$required_month_obj = clone $current_date; // Default: Current Month

if ($latest_proof_date_obj) {
    // Start checking from the month AFTER the last submission
    $required_month_obj = clone $latest_proof_date_obj;
    $required_month_obj->modify('+1 month'); 

    // If the month of the last proof is the SAME as the current month (meaning you just uploaded this month)
    // we should ask for the next month's proof.
    if ($latest_proof_date_obj->format('Y-m') == $current_date->format('Y-m')) {
        $required_month_obj = clone $current_date;
        $required_month_obj->modify('+1 month');
    }
    
} 

// FINAL CHECK: Ensure the displayed month is not too far in the past. 
// If the calculated required month is BEFORE the current month (i.e., you are late by several months), 
// we should just prompt the user for the CURRENT month's proof.
if ($required_month_obj->format('Y-m') < $current_date->format('Y-m')) {
    $required_month_obj = $current_date;
}

// The month to display in the form (e.g., "October 2025")
$month_to_display = $required_month_obj->format('F Y');
// =========================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Proof</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="style.css"> 
<style>
/* General Styles (assuming you have a base style.css, these are overrides/additions) */
body { display: flex; font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
.sidebar { /* Your sidebar styles from client_dashboard.php */
    width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 25px 20px;
    display: flex; flex-direction: column; position: fixed; z-index: 1000;
}
.sidebar .logo-box { display: flex; justify-content: left; align-items: center; padding: 15px 0; margin-bottom: 30px; border-radius: 8px;}
.sidebar .logo-box img { height: 60px; width: auto; border-radius: 6px; }
.sidebar a { color: #e2e8f0; text-decoration: none; padding: 10px; margin-bottom: 8px; border-radius: 6px; display: block; transition: 0.3s; }
.sidebar a:hover { background: #1e293b; color: #fff; }

.main { flex: 1; padding: 30px 40px; margin-left: 230px; }
.main header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
.main header h1 { font-size: 22px; font-weight: 600; color: #1e3a8a; }
.main header .profile { font-weight: 500; color: #2563eb; }

.content-layout {
    display: grid;
    grid-template-columns: 1fr 1fr; /* Two columns */
    gap: 30px;
}

.upload-card, .history-section {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.upload-card { border-top: 5px solid #059669; text-align:center; }
.history-section { border-top: 5px solid #f97316; }

.upload-card h2 { color:#059669; margin-bottom:10px; font-weight:700; font-size: 24px; }
.history-section h2 { color: #f97316; margin-bottom: 20px; font-weight: 700; font-size: 24px; }
.upload-card .instructions { color: #475569; margin-bottom: 25px; line-height: 1.6; }

.drop-zone {
    border: 3px dashed #93c5fd; background-color: #f7faff;
    padding: 40px 20px; border-radius: 10px; cursor: pointer;
    color: #2563eb; margin-bottom: 20px;
    transition: background-color 0.3s, border-color 0.3s;
}
.drop-zone.drag-over {
    background-color: #e0f2fe;
    border-color: #38bdf8;
}
.drop-zone p { margin-top: 10px; font-size: 1.1em; }

.image-preview {
    margin-top: 15px; border: 1px solid #e2e8f0; padding: 15px;
    border-radius: 8px; background: #f1f5f9; text-align: left;
    display: flex; flex-direction: column; align-items: center; /* Center content */
}
.image-preview img { max-width: 100%; max-height: 200px; object-fit: contain; display: block; margin: 10px auto 15px auto; border-radius: 6px; }
.image-preview #fileNameDisplay { font-weight: 500; color: #334155; }

.upload-card button {
    background:#2563eb; color: #fff; padding:12px 25px; border-radius:8px;
    font-weight: 600; width: 100%; border:none; cursor:pointer;
    transition: background 0.3s ease;
}
.upload-card button:hover:not(:disabled) { background:#1d4ed8; }
.upload-card button:disabled { background:#cbd5e1; cursor: not-allowed; }


.history-section table { width: 100%; border-collapse: collapse; font-size: 15px; margin-top: 20px; }
.history-section th, .history-section td { padding: 15px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
.history-section th { background-color: #fef3c7; color: #78350f; font-weight: 600; text-transform: uppercase; }
.history-section td img { height: 60px; width: auto; border-radius: 4px; object-fit: cover; cursor: pointer; transition: transform 0.2s; }
.history-section td img:hover { transform: scale(1.05); }

.message { margin-bottom:20px; padding:12px 20px; border-radius:8px; font-weight:600; text-align: left; }
.success { background:#d1fae5; color:#065f46; border: 1px solid #34d399; }
.error { background:#fee2e2; color:#991b1b; border: 1px solid #f87171; }

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    padding-top: 80px;
    left: 0; top: 0; width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.9);
}
.modal-content {
    display: block;
    margin: auto;
    max-width: 80%;
    max-height: 80%;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(255,255,255,0.3);
    animation: zoomIn 0.3s ease;
}
@keyframes zoomIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.close {
    position: absolute;
    top: 40px;
    right: 60px;
    color: #fff;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover { color: #f87171; }
.prev, .next {
    cursor: pointer;
    position: absolute;
    top: 50%;
    padding: 16px;
    color: white;
    font-weight: bold;
    font-size: 40px;
    transition: 0.3s;
    user-select: none;
}
.next { right: 40px; }
.prev { left: 40px; }
.prev:hover, .next:hover { color: #38bdf8; }
#caption {
    text-align: center;
    color: #f1f5f9;
    padding: 10px;
    font-size: 16px;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .content-layout {
        grid-template-columns: 1fr; /* Stack columns on smaller screens */
    }
    .main { margin-left: 0; padding: 20px; }
    .sidebar { position: static; width: 100%; min-height: auto; }
    .main header { flex-direction: column; align-items: flex-start; gap: 15px; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php">💼 Loans</a>
    <a href="my_payments.php">💰 Payments</a>
    <a href="upload_photo.php" class="active">📸 Upload Proof</a> <a href="my_history.php">📜 History</a>
    <a href="logout.php">🚪 Logout</a>
</aside>

<main class="main">
    <header>
        <h1>Upload Monthly Proof</h1>
        <div class="profile">👤 <?= htmlspecialchars(ucfirst($user['role'])) ?></div>
    </header>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="content-layout">
        <div class="upload-card">
            <h2>Submit Monthly Progress Photo</h2>
            <p class="instructions">Please upload a clear, current photo (JPG, PNG, or GIF, max 5MB) showing your business or loan progress for <strong><?= $month_to_display ?></strong>.</p>
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <div class="drop-zone" id="dropZone">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p>Drag & Drop your photo here or Click to browse</p>
                    <input type="file" name="photo" id="fileInput" accept="image/jpeg,image/png,image/gif" required style="display: none;">
                </div>
                <div id="imagePreview" class="image-preview" style="display: none;">
                    <img id="previewImage" src="#" alt="Image Preview">
                    <span id="fileNameDisplay"></span>
                </div>
              <button type="submit" id="submitButton" disabled>Submit Proof</button>
            </form>
        </div>

        <?php if ($proof_history): ?>
            <div class="history-section">
                <h2><i class="fas fa-history"></i> Proof Upload History (Last 10)</h2>
                <table>
                    <thead>
                        <tr><th>Date Uploaded</th><th>File Name</th><th>Preview</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proof_history as $proof): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($proof['submitted_date']))) ?></td>
                                <td><?= htmlspecialchars($proof['filename']) ?></td>
                                <td><img src="uploads/<?= htmlspecialchars($proof['filename']) ?>" alt="Uploaded proof for <?= htmlspecialchars(date('M Y', strtotime($proof['submitted_date']))) ?>" class="preview-thumb" data-full="uploads/<?= htmlspecialchars($proof['filename']) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="history-section" style="text-align: center;">
                <h2><i class="fas fa-history"></i> Proof Upload History</h2>
                <p style="margin-top: 20px; color: #475569;">No previous proofs found. Please upload your first photo proof!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<div id="imageModal" class="modal">
  <span class="close">&times;</span>
  <img class="modal-content" id="modalImage">
  <div id="caption"></div>
  <a class="prev">&#10094;</a>
  <a class="next">&#10095;</a>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const previewContainer = document.getElementById('imagePreview');
const previewImage = document.getElementById('previewImage');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const submitButton = document.getElementById('submitButton');

// Upload Preview Logic
dropZone.addEventListener('click', () => fileInput.click());
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => { dropZone.addEventListener(e, preventDefaults, false); });
['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('drag-over'), false));
['dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('drag-over'), false));
dropZone.addEventListener('drop', handleDrop, false);
fileInput.addEventListener('change', e => handleFiles(e.target.files));

function preventDefaults(e){ e.preventDefault(); e.stopPropagation(); }

function handleDrop(e){
    const dt=e.dataTransfer;
    const files=dt.files;
    if(files.length){
        fileInput.files=files; // Assign files to the input for form submission
        handleFiles(files);
    }
}

function handleFiles(files){
    if(files.length===0){
        previewContainer.style.display='none';
        submitButton.disabled=true;
        return;
    }
    const file=files[0];

    // Client-side file type and size validation
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    const maxSize = 5 * 1024 * 1024; // 5MB

    if(!allowedTypes.includes(file.type)){
        alert('Please select an image file (JPG, PNG, or GIF).');
        fileInput.value=''; // Clear the input
        previewContainer.style.display='none';
        submitButton.disabled=true;
        return;
    }
    if(file.size > maxSize){
        alert('File is too large. Maximum size is 5MB.');
        fileInput.value='';
        previewContainer.style.display='none';
        submitButton.disabled=true;
        return;
    }

    const reader=new FileReader();
    reader.onload=function(e){
        previewImage.src=e.target.result;
        fileNameDisplay.textContent='File selected: '+file.name;
        previewContainer.style.display='flex'; // Use flex for centering
        submitButton.disabled=false;
    };
    reader.readAsDataURL(file);
}


// Modal Logic with Navigation
const modal=document.getElementById("imageModal");
const modalImg=document.getElementById("modalImage");
const captionText=document.getElementById("caption");
const closeBtn=document.querySelector(".close");
const prevBtn=document.querySelector(".prev");
const nextBtn=document.querySelector(".next");
let currentIndex=0;
const thumbnails=Array.from(document.querySelectorAll(".preview-thumb"));

function openModal(index){
    if (thumbnails.length === 0) return; // No images to show

    currentIndex = index;
    modal.style.display="block";
    updateModal();
}

function updateModal(){
    const img=thumbnails[currentIndex];
    modalImg.src=img.dataset.full;
    captionText.textContent=img.alt || "Proof Image"; // Fallback caption
}

thumbnails.forEach((img,index)=>img.addEventListener("click",()=>openModal(index)));
closeBtn.addEventListener("click",()=>modal.style.display="none");
window.addEventListener("click",e=>{ if(e.target===modal) modal.style.display="none"; });

prevBtn.addEventListener("click",()=>{
    if (thumbnails.length === 0) return;
    currentIndex=(currentIndex-1+thumbnails.length)%thumbnails.length;
    updateModal();
});
nextBtn.addEventListener("click",()=>{
    if (thumbnails.length === 0) return;
    currentIndex=(currentIndex+1)%thumbnails.length;
    updateModal();
});

window.addEventListener("keydown",e=>{
    if(modal.style.display==="block"){
        if(e.key==="ArrowLeft") prevBtn.click();
        if(e.key==="ArrowRight") nextBtn.click();
        if(e.key==="Escape") closeBtn.click();
    }
});
</script>
</body>
</html>