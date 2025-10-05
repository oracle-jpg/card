<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

function sendAccountEmail($recipientEmail, $recipientName, $username, $password_plain) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Gmail SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'pagtalunanarchie30@gmail.com'; // 🔹 your Gmail address
        $mail->Password = 'cloqyzqeurhjuqky'; // 🔹 16-char App Password from Google
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Sender and recipient
        $mail->setFrom('pagtalunanarchie30@gmail.com', 'Microfinance System');
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Microfinance Account Details';
        $mail->Body = "
            <h2>Welcome to Microfinance System</h2>
            <p>Hi <b>$recipientName</b>,</p>
            <p>Your account has been created successfully.</p>
            <p><b>Username:</b> $username<br>
               <b>Password:</b> $password_plain</p>
            <p>Please login and change your password immediately for security.</p>
            <p><small>This is an automated email, please do not reply.</small></p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
