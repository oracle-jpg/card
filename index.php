<?php
require_once 'db.php';
session_start();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash, role, full_name FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "No account found. Please <a href='register.php'>register here</a>.";
    } elseif (password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        if ($user['role'] === 'client') header('Location: client_dashboard.php');
        elseif ($user['role'] === 'staff') header('Location: staff_dashboard.php');
        elseif ($user['role'] === 'manager') header('Location: manager_dashboard.php');
        else header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = "Invalid password. Please try again.";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login - CARD RBI</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: url("card.jpg") no-repeat center center fixed;
      background-size: cover;
      font-family: 'Segoe UI', Arial, sans-serif;
    }

    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-box {
      background: rgba(255,255,255,0.85); /* semi-transparent white */
      padding: 30px;
      border-radius: 12px;
      width: 350px;
      box-shadow: 0px 5px 20px rgba(0,0,0,0.5);
      text-align: center;
    }

    .login-box img {
      width: 140px;
      margin-bottom: 15px;
    }

    .login-box h2 {
      margin-bottom: 20px;
      color: #0033A0;
    }

    .login-box label {
      display: block;
      text-align: left;
      margin-top: 10px;
      font-weight: bold;
    }

    .login-box input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    .login-box button {
      margin-top: 15px;
      width: 100%;
      background: #0033A0;
      color: white;
      padding: 10px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
    }
    .login-box button:hover {
      background: #FFD700;
      color: #0033A0;
    }

    .login-box p {
      margin-top: 12px;
      font-size: 14px;
    }

    .login-box a {
      color: #0033A0;
      font-weight: bold;
      text-decoration: none;
    }
    .login-box a:hover {
      color: #FFD700;
    }
  </style>
</head>
<body>
  
  <div class="login-container">
    <div class="login-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo"> <!-- Add your logo here -->
      <h2>Login to CARD RBI</h2>
      <?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
      <form method="post">
        <label>Username</label>
        <input type="text" name="username" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Login</button>
      </form>
      <p>Don’t have an account? <a href="register.php">Register here</a></p>
    </div>
  </div>
</body>
</html>
