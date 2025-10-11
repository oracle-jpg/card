<?php
// forgot_password.php
require_once 'db.php'; // Include your database connection

// === PHPMailer Autoload (Adjust path based on your installation) ===
// If you installed via Composer
// If you downloaded manually and placed src folder in your project root
require 'PHPMailer/PHPMailer.php';   // Corrected path for PHPMailer.php
require 'PHPMailer/SMTP.php';       // Corrected path for SMTP.php
require 'PHPMailer/Exception.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// ===================================================================

$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = trim($_POST['email_or_username']);

    // Find the user by username or email
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$email_or_username, $email_or_username]);
    $user = $stmt->fetch();

    if ($user && !empty($user['email'])) { // Only proceed if user found and has an email
        $user_id = $user['id'];
        $user_email = $user['email'];
        $user_full_name = $user['full_name'] ?? 'Client'; // Default name if not set

        // Generate a unique token
        $reset_token = bin2hex(random_bytes(32)); // 64-character hex string
        $expiry_time = date('Y-m-d H:i:s', strtotime('+1 day')); // Token valid for 1 day (for testing)// Token valid for 1 hour

        // Store the token and expiry in the database
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
        $stmt->execute([$reset_token, $expiry_time, $user_id]);

        // Construct the reset link
        $reset_link = "http://localhost/card/reset_password.php?token=" . $reset_token; // ADD '/card/' // IMPORTANT: Change 'localhost' to your actual domain name!

        // === Send Email using PHPMailer ===
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                       // Set the SMTP server to send through (e.g., smtp.gmail.com, smtp.office365.com)
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'pagtalunanarchie30@gmail.com';                 // SMTP username (Your actual email address)
            $mail->Password   = 'cloqyzqeurhjuqky';                    // SMTP password (If using Gmail, generate an App Password)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // Enable implicit TLS encryption
            $mail->Port       = 465;                                    // TCP port to connect to; use 587 for `PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom('your_email@gmail.com', 'CARD RBI Microfinance'); // Sender's email and name
            $mail->addAddress($user_email, $user_full_name);            // Add a recipient

            //Content
            $mail->isHTML(true);                                        // Set email format to HTML
            $mail->Subject = 'Password Reset Request for CARD RBI Microfinance';
            $mail->Body    = '
                <html>
                <head>
                    <title>Password Reset Request</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { width: 80%; margin: 20px auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9; }
                        .header { background-color: #0033A0; color: white; padding: 10px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .button { display: inline-block; padding: 10px 20px; margin: 20px 0; background-color: #FFD700; color: #0033A0; text-decoration: none; border-radius: 5px; font-weight: bold; }
                        .footer { margin-top: 30px; font-size: 0.9em; color: #777; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>Password Reset Request</h2>
                        </div>
                        <p>Hello ' . htmlspecialchars($user_full_name) . ',</p>
                        <p>We received a request to reset your password for your CARD RBI Microfinance account.</p>
                        <p>To reset your password, please click the link below:</p>
                        <a href="' . htmlspecialchars($reset_link) . '" class="button">Reset Your Password</a>
                        <p>If the button above does not work, you can copy and paste the following URL into your web browser:</p>
                        <p><a href="' . htmlspecialchars($reset_link) . '">' . htmlspecialchars($reset_link) . '</a></p>
                        <p>This link will expire in 1 hour for security reasons.</p>
                        <p>If you did not request a password reset, please ignore this email.</p>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' CARD RBI Microfinance. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';
            $mail->AltBody = 'Hello ' . $user_full_name . ',\n\nWe received a request to reset your password for your CARD RBI Microfinance account. To reset your password, please visit this link: ' . $reset_link . '\n\nThis link will expire in 1 hour. If you did not request a password reset, please ignore this email.\n\nThank you,\nCARD RBI Microfinance';

            $mail->send();
            $message = "If an account with that email or username exists, a password reset link has been sent to your registered email address.";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Failed to send reset email. Mailer Error: {$mail->ErrorInfo}";
            $message_type = 'error';
            // Log the error for debugging: error_log("Password reset email failed: {$mail->ErrorInfo}");
        }
        // === End PHPMailer ===

    } else {
        // For security, always give a generic success message to prevent user enumeration
        $message = "If an account with that email or username exists, a password reset link has been sent to your registered email address.";
        $message_type = 'success';
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - CARD RBI Microfinance</title>
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
      <h2>Forgot Your Password?</h2>
      <p>Enter your username or email address below and we'll send you instructions to reset your password.</p>

      <?php if(!empty($message)): ?>
          <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
      <?php endif; ?>

      <form method="post">
        <label for="email_or_username">Username or Email</label>
        <input type="text" id="email_or_username" name="email_or_username" placeholder="e.g., juan_delacruz or juan@example.com" required>
        <button type="submit">Send Reset Link</button>
      </form>

      <a href="index.php" class="back-to-login">Back to Login</a>
    </div>
  </div>

</body>
</html>