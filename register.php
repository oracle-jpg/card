<?php
require 'db.php';
session_start();

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $age = (int)$_POST['age'];
    $password = $_POST['password'];

    if ($age < 18) {
        $error = "You must be at least 18 years old to register.";
    } else {
        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->rowCount() > 0) {
            $error = "Username or Email already exists. Please choose another.";
        } else {
            $role = 'client';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = rand(100000, 999999); // 6-digit code

            try {
                $pdo->beginTransaction();

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, full_name, email, phone, address, age, email_verification_code, is_email_verified)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$username, $hash, $role, $fullname, $email, $phone, $address, $age, $verification_code]);

                $user_id = $pdo->lastInsertId();

                // Insert into members table
                $stmtMember = $pdo->prepare("
                    INSERT INTO members (user_id, name, address, phone, age, loan_amount, loan_start, status, created_by)
                    VALUES (?, ?, ?, ?, ?, 0.00, CURDATE(), 'active', NULL)
                ");
                $stmtMember->execute([$user_id, $fullname, $address, $phone, $age]);

                $pdo->commit();

                // Send verification email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'pagtalunanarchie30@gmail.com'; // Your Gmail
                    $mail->Password = 'cloqyzqeurhjuqky'; // App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('pagtalunanarchie30@gmail.com', 'CARD RBI');
                    $mail->addAddress($email, $fullname);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your CARD RBI Email Verification Code';
                    $mail->Body = "
                        Hi <b>$fullname</b>,<br><br>
                        Thank you for registering at <b>CARD RBI Microfinance</b>!<br>
                        Your verification code is:<br><br>
                        <h2 style='color:#0033A0;'>$verification_code</h2><br>
                        Enter this code on the verification page to activate your account.<br><br>
                        If you didn’t register, you can ignore this email.<br><br>
                        Best regards,<br>
                        CARD RBI Team
                    ";

                    $mail->send();

                    $_SESSION['verify_email'] = $email;
                    header("Location: verify_code.php");
                    exit;

                } catch (Exception $e) {
                    $error = "Registration succeeded, but the email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed. Please try again. " . $e->getMessage();
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Register - CARD RBI</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
        --primary-color: #0056b3;
        --secondary-color: #007bff;
        --background-gradient: linear-gradient(135deg, #e0f2f7 0%, #cce7f5 100%);
        --text-color: #333;
        --light-text-color: #666;
        --border-color: #e0e0e0;
        --input-focus-shadow: rgba(0, 123, 255, 0.25);
        --error-bg: #ffe0e0;
        --error-text: #d32f2f;
        --box-shadow: 0px 10px 30px rgba(0,0,0,0.15);
    }

    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('card.jpg') no-repeat center center fixed;
      background-size: cover;
      font-family: 'Poppins', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      color: var(--text-color);
    }

    .register-container {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      width: 100%;
    }

   .register-box {
  background: #ffffff;
  padding: 40px;
  border-radius: 15px;
  width: 100%;
  max-width: 450px;
  box-shadow: var(--box-shadow);
  animation: fadeIn 0.8s ease-out forwards;
  text-align: left; /* <-- Align all text to the left */
}
.register-box img,
.register-box h2 {
  display: block;
  margin-left: auto;
  margin-right: auto;
  text-align: center;
}


    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .register-box img {
      width: 130px;
      margin-bottom: 25px;
    }

    .register-box h2 {
      margin-bottom: 30px;
      color: var(--primary-color);
      font-weight: 700;
      font-size: 28px;
    }

    .error-message-box {
        background-color: var(--error-bg);
        color: var(--error-text);
        border: 1px solid var(--error-text);
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 25px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .register-box input {
      width: 100%;
      padding: 13px 15px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 15.5px;
      transition: 0.3s ease;
    }

    .register-box input:focus {
      border-color: var(--secondary-color);
      box-shadow: 0 0 0 3px var(--input-focus-shadow);
      outline: none;
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
    }

    .register-box button {
      margin-top: 35px;
      width: 100%;
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 15px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      font-size: 18px;
      transition: 0.3s;
    }

    .register-box button:hover {
      background-color: var(--secondary-color);
      transform: translateY(-3px);
    }

    .register-box p {
      margin-top: 30px;
      font-size: 14.5px;
      color: var(--light-text-color);
    }

    .register-box a {
      color: var(--primary-color);
      font-weight: 600;
      text-decoration: none;
    }

  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo">
      <h2>Create Your Client Account</h2>

      <?php if (!empty($error)): ?>
        <div class="error-message-box">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g., juan_delacruz" required>

        <label>Full Name</label>
        <input type="text" name="full_name" placeholder="Your complete name" required>

        <label>Email</label>
        <input type="email" name="email" placeholder="e.g., you@example.com" required>

        <label>Phone</label>
        <input type="tel" name="phone" pattern="[0-9]{11}" placeholder="09xxxxxxxxx (11 digits)" required>

        <label>Address</label>
        <input type="text" name="address" placeholder="House#, Street, Barangay, City" required>

        <label>Age (Must be 18 or older)</label>
        <input type="number" name="age" min="18" required>

        <label>Password</label>
        <div class="input-group" style="position: relative;">
          <input type="password" id="password" name="password" placeholder="Create a strong password" required>
          <i class="fas fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <button type="submit">Register Account</button>
      </form>
      <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
  </div>

  <script>
    // Password toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      this.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>
