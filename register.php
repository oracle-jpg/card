<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $role = 'client';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $role, $fullname, $email]);

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
  <link rel="stylesheet" href="style.css">
</head>
<body class="client">
  <header>
    <h1>CARD RBI Registration</h1>
  </header>

  <form method="post">
    <h2 style="text-align:center; color:#2D8CFF;">Create Client Account</h2>
    <?php if(!empty($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Full Name</label>
    <input type="text" name="full_name" required>
    <label>Email</label>
    <input type="email" name="email" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn">Register</button>
    <p style="text-align:center; margin-top:10px;">
      Already have an account? <a href="index.php">Login here</a>
    </p>
  </form>

  <footer>
    Ito ang bangko natin.
  </footer>
</body>
</html>
