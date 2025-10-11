<?php
// reset_password.php
require_once 'db.php'; // Include your database connection

$message = '';
$message_type = ''; // 'success' or 'error'
$token = $_GET['token'] ?? ''; // Get token from URL

// 1. Verify the token
if (empty($token)) {
    $message = "Invalid or missing password reset token.";
    $message_type = 'error';
} else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = "Invalid or expired password reset token. Please try again.";
        $message_type = 'error';
    } else {
        // Token is valid, allow user to set new password
        $user_id = $user['id'];

        // Handle form submission for new password
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($new_password) || empty($confirm_password)) {
                $message = "Please enter and confirm your new password.";
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = "Passwords do not match. Please try again.";
                $message_type = 'error';
            } elseif (strlen($new_password) < 8) { // Basic password policy
                $message = "Password must be at least 8 characters long.";
                $message_type = 'error';
            } else {
                // Hash the new password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password and invalidate token
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);

                $message = "Your password has been successfully reset. You can now log in with your new password.";
                $message_type = 'success';

                // Optional: Redirect to login page after successful reset
                // header('Location: login.php?reset=success');
                // exit;
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - CARD RBI Microfinance</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('card.jpg') no-repeat center center fixed;
      background-size: cover;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #333;
    }
    .container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .box {
      background: rgba(255,255,255,0.98);
      padding: 25px 40px;
      border-radius: 12px;
      width: 400px;
      max-width: 90%;
      box-shadow: 0px 8px 25px rgba(0,0,0,0.3);
      text-align: center;
      animation: fadeIn 0.8s ease-out forwards;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .box img {
      width: 180px;
      margin-bottom: 25px;
    }
    .box h2 {
      margin-bottom: 25px;
      color: #0033A0;
      font-size: 28px;
    }
    .box p {
      margin-bottom: 20px;
      color: #666;
      line-height: 1.5;
    }
    .box label {
      display: block;
      text-align: left;
      margin-top: 15px;
      margin-bottom: 5px;
      font-weight: bold;
      color: #555;
      font-size: 15px;
    }
    .box input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      font-size: 16px;
      box-sizing: border-box;
    }
    .box input:focus {
      border-color: #0033A0;
      box-shadow: 0 0 0 3px rgba(0, 51, 160, 0.2);
      outline: none;
    }
    .box button {
      margin-top: 25px;
      width: 100%;
      background: #0033A0;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      font-size: 17px;
      transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
    }
    .box button:hover {
      background: #FFD700;
      color: #0033A0;
      transform: translateY(-2px);
    }
    .box .back-to-login {
      margin-top: 20px;
      width: 100%;
      background: #e2e8f0;
      color: #2d3748;
      padding: 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: block;
      box-sizing: border-box;
      font-size: 16px;
      transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
    }
    .box .back-to-login:hover {
      background: #cbd5e1;
      color: #1a202c;
      transform: translateY(-2px);
    }
    .message {
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 20px;
        text-align: left;
        font-size: 14px;
    }
    .message.success {
        background-color: #e6ffed;
        color: #0f6f32;
        border: 1px solid #0f6f32;
    }
    .message.error {
        background-color: #ffebeb;
        color: #d8000c;
        border: 1px solid #d8000c;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo">
      <h2>Reset Your Password</h2>

      <?php if(!empty($message)): ?>
          <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
      <?php endif; ?>

      <?php if ($message_type !== 'error' && !empty($user_id)): // Only show form if token is valid and no error yet ?>
      <form method="post">
        <p>Note: Password must be at least 8 characters long.</p>
        <label for="new_password">New Password</label>
        
        <input type="password" id="new_password" name="new_password" placeholder="••••••••" required>

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
        <button type="submit">Set New Password</button>
      </form>
      <?php endif; ?>

      <a href="index.php" class="back-to-login">Back to Login</a>
    </div>
  </div>

</body>
</html>