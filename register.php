<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    $role = 'client';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email,phone) VALUES (?, ?, ?, ?, ?,?)");
        $stmt->execute([$username, $hash, $role, $fullname, $email,$phone]);

        $_SESSION['user_id'] = $pdo->lastInsertId();
        header("Location: client_dashboard.php");
        exit;
    } catch (PDOException $e) {
        $error = "Username already exists!";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register - CARD RBI</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: url("card.jpg") no-repeat center center fixed;
      background-size: cover;
      font-family: 'Segoe UI', Arial, sans-serif;
    }

    .register-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .register-box {
      background: rgba(255,255,255,0.85);
      padding: 30px;
      border-radius: 12px;
      width: 380px;
      box-shadow: 0px 5px 20px rgba(0,0,0,0.5);
      text-align: center;
    }

    .register-box img {
      width: 140px;
      margin-bottom: 15px;
    }

    .register-box h2 {
      margin-bottom: 20px;
      color: #2D8CFF;
    }

    .register-box label {
      display: block;
      text-align: left;
      margin-top: 10px;
      font-weight: bold;
    }

    .register-box input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    .register-box button {
      margin-top: 15px;
      width: 100%;
      background-color: #0033A0;
      color: white;
      border: none;
      padding: 10px;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
    }
    .register-box button:hover {
      background-color: #FFD700;
      color: #0033A0;
    }

    .register-box p {
      margin-top: 12px;
      font-size: 14px;
    }

    .register-box a {
      color: #0033A0;
      font-weight: bold;
      text-decoration: none;
    }
    .register-box a:hover {
      color: #FFD700;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo">
      <h2>Create Client Account</h2>
      <?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
      <form method="post">
        <label>Username</label>
        <input type="text" name="username" required>
        <label>Full Name</label>
        <input type="text" name="full_name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Phone Number</label>
        <input type="text" name="phone" placeholder="e.g., 09xxxxxxxxx">
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Register</button>
      </form>
      <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
  </div>
</body>
</html>
