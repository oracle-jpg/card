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
        else header('Location: dashboard.php');
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
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <h1>CARD RBI System</h1>
  </header>

  <form method="post">
    <h2 style="text-align:center; color:#0033A0;">Login</h2>
    <?php if(!empty($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn">Login</button>
    <p style="text-align:center; margin-top:10px;">
      Don’t have an account? <a href="register.php">Register here</a>
    </p>
  </form>

  <footer>
    Ito ang bangko natin.
  </footer>
</body>
</html>
