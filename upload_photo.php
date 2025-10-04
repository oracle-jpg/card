<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the client's member_id
$stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_id = $member ? $member['id'] : null;

if (!$member_id) {
    die("❌ Error: Your account is not linked to any member record. Please contact support.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    // Ensure uploads folder exists
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    $target_dir = "uploads/";
    $filename = basename($_FILES["photo"]["name"]);
    $target_file = $target_dir . $filename;
    $submitted_date = date('Y-m-d');

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        $stmt = $pdo->prepare("INSERT INTO member_photos (member_id, uploaded_by, filename, submitted_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$member_id, $user_id, $filename, $submitted_date]);

        echo "<script>alert('✅ Photo uploaded successfully!'); window.location='client_dashboard.php';</script>";
        exit;
    } else {
        echo "<p style='color:red;'>❌ Upload failed. Please check folder permissions.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Monthly Proof</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 50px;
        }
        .upload-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            margin: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        input[type="file"] {
            display: block;
            margin: 20px 0;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="upload-container">
    <h2>📸 Upload Monthly Proof</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Select a file to upload:</label>
        <input type="file" name="photo" required>
        <button type="submit">Upload</button>
    </form>
</div>
</body>
</html>
