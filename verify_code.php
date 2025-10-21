<?php
require 'db.php';
session_start();

if (!isset($_SESSION['verify_email'])) {
    header("Location: register.php");
    exit;
}

$email = $_SESSION['verify_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND email_verification_code = ?");
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_email_verified = 1, email_verification_code = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        unset($_SESSION['verify_email']);
        $success = "✅ Email verified successfully!";
    } else {
        $error = "❌ Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Verify Your Email - CARD RBI</title>
  <style>
    body {
      font-family: Arial;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      background: #f3f3f3;
    }
    .box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      width: 350px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    input {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
    }
    button {
      width: 100%;
      padding: 10px;
      background: #0033A0;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    button:hover {
      background: #FFD700;
      color: #0033A0;
    }
    .message {
      margin-bottom: 10px;
      color: red;
    }
    .success {
      color: green;
      font-weight: bold;
    }
    .login-link {
      display: inline-block;
      margin-top: 15px;
      text-decoration: none;
      color: #0033A0;
      font-weight: bold;
    }
    .login-link:hover {
      color: #FFD700;
    }
  </style>
</head>
<body>
  <div class="box">
    <h2>Email Verification</h2>

    <?php if(!empty($error)): ?>
      <p class="message"><?= $error ?></p>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
      <p class="message success"><?= $success ?></p>
      <a href="index.php" class="login-link">Return to Login</a>
    <?php else: ?>
      <form method="post">
        <label>Enter the 6-digit code sent to your email:</label>
        <input type="text" name="code" maxlength="6" required>
        <button type="submit">Verify</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
