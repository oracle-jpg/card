<?php
session_start();
require 'database.php';

// Check if logged in at role = staff
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'staff') {
    echo "Access denied.";
    exit;
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $loanAmount = $_POST['loan_amount'];

    $stmt = $pdo->prepare("INSERT INTO members (name, address, phone, loan_amount, status, created_by) 
                           VALUES (?, ?, ?, ?, 'active', ?)");
    $stmt->execute([$name, $address, $phone, $loanAmount, $_SESSION['user_id']]);

    $message = "✅ New member added successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Member</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h2>Add New Member (Staff)</h2>
        <?php if($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <form method="post" class="card p-3">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Initial Loan Amount</label>
                <input type="number" name="loan_amount" step="0.01" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Add Member</button>
        </form>
    </div>
</body>
</html>
