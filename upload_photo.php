<?php
session_start();
require 'db.php';
require 'auth.php';

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
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
             $message = "❌ Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("INSERT INTO member_photos (member_id, filename, submitted_date) VALUES (?, ?, NOW())");
            $stmt->execute([$member_id, $filename]);
            // After successful upload
log_action($pdo, $user['id'], 'Uploaded a new payment proof.');

            $message = "✅ Proof uploaded successfully!";
            
        } else {
            $message = "❌ Failed to save file on the server.";
        }
    } else {
        $message = "❌ Failed to upload file: " . $file['error'];
    }
}

// === Fetch Proof History ===
$stmt = $pdo->prepare("
    SELECT * FROM member_photos 
    WHERE member_id = ? 
    ORDER BY submitted_date DESC 
    LIMIT 10 
"); 
$stmt->execute([$member_id]);
$proof_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_month = date('F Y');
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
.upload-card { border-top: 5px solid #059669; text-align:center; height: 100%; }
.history-section { border-top: 5px solid #f97316; height: 100%; }
.upload-card h2 { color:#059669; margin-bottom:10px; font-weight:700; }
.history-section h2 { color: #f97316; margin-bottom: 20px; font-weight: 700; }
.main header { border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }

.drop-zone {
    border: 3px dashed #93c5fd; background-color: #f7faff;
    padding: 40px 20px; border-radius: 10px; cursor: pointer;
    color: #2563eb; margin-bottom: 20px;
}
.image-preview {
    margin-top: 15px; border: 1px solid #e2e8f0; padding: 15px;
    border-radius: 8px; background: #f1f5f9; text-align: left;
}
.image-preview img { max-width: 100%; max-height: 250px; display: block; margin: 10px auto 15px auto; border-radius: 6px; }
.upload-card button { background:#2563eb; padding:12px 25px; border-radius:8px; font-weight: 600; width: 100%; border:none; cursor:pointer; }

.history-section table { width: 100%; border-collapse: collapse; font-size: 15px; }
.history-section th, .history-section td { padding: 15px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
.history-section th { background-color: #fef3c7; color: #78350f; font-weight: 600; text-transform: uppercase; }
.history-section td img { height: 50px; width: auto; border-radius: 4px; cursor: pointer; transition: transform 0.2s; }
.history-section td img:hover { transform: scale(1.1); }

.message { margin-bottom:15px; padding:10px; border-radius:6px; font-weight:600; text-align: left; }
.success { background:#d1fae5; color:#065f46; }
.error { background:#fee2e2; color:#991b1b; }

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
  <a href="upload_photo.php">📸 Upload Proof</a>
  <a href="my_history.php">📜 History</a>

</aside>

<main class="main">
  <header>
    <h1>Upload Monthly Proof</h1>
    <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
  </header>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="content-layout">
        <div class="upload-card">
            <h2>Submit Monthly Progress Photo</h2>
            <p class="instructions">Please upload a clear, current photo (JPG/PNG) showing your business or loan progress for <strong><?= $current_month ?></strong>.</p>
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <div class="drop-zone" id="dropZone">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p>Drag & Drop your photo here or Click to browse</p>
                    <input type="file" name="photo" id="fileInput" accept="image/jpeg, image/png" required style="display: none;">
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
                                <td><img src="uploads/<?= htmlspecialchars($proof['filename']) ?>" alt="Uploaded proof" class="preview-thumb" data-full="uploads/<?= htmlspecialchars($proof['filename']) ?>"></td>
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

<!-- Modal for Image Preview -->
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

// Upload Preview
dropZone.addEventListener('click', () => fileInput.click());
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => { dropZone.addEventListener(e, preventDefaults, false); });
['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('drag-over'), false));
['dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('drag-over'), false));
dropZone.addEventListener('drop', handleDrop, false);
fileInput.addEventListener('change', e => handleFiles(e.target.files));
function preventDefaults(e){ e.preventDefault(); e.stopPropagation(); }
function handleDrop(e){ const dt=e.dataTransfer; const files=dt.files; if(files.length){ fileInput.files=files; handleFiles(files); } }
function handleFiles(files){
  if(files.length===0){ previewContainer.style.display='none'; submitButton.disabled=true; return; }
  const file=files[0];
  if(!file.type.startsWith('image/')){ alert('Please select an image file (JPG or PNG).'); fileInput.value=''; previewContainer.style.display='none'; submitButton.disabled=true; return; }
  const reader=new FileReader();
  reader.onload=function(e){ previewImage.src=e.target.result; fileNameDisplay.textContent='File selected: '+file.name; previewContainer.style.display='block'; submitButton.disabled=false; };
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

function openModal(index){ currentIndex=index; modal.style.display="block"; updateModal(); }
function updateModal(){
  const img=thumbnails[currentIndex];
  modalImg.src=img.dataset.full;
  captionText.textContent=img.alt || img.dataset.full;
}
thumbnails.forEach((img,index)=>img.addEventListener("click",()=>openModal(index)));
closeBtn.addEventListener("click",()=>modal.style.display="none");
window.addEventListener("click",e=>{ if(e.target===modal) modal.style.display="none"; });
prevBtn.addEventListener("click",()=>{ currentIndex=(currentIndex-1+thumbnails.length)%thumbnails.length; updateModal(); });
nextBtn.addEventListener("click",()=>{ currentIndex=(currentIndex+1)%thumbnails.length; updateModal(); });
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
