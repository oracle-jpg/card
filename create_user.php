<?php
require_once 'auth.php';
require_role(['admin']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (!in_array($role, ['staff', 'manager'])) {
        $msg = "Invalid role. Only Staff/Manager allowed.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $role, $fullname, $email]);
            $msg = "User ($role) created successfully.";
        } catch (PDOException $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Staff/Manager</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="admin">
  <header>
    <h1>Operations Manager Panel</h1>
    <nav>
      <a href="admin_dashboard.php">Home</a>
      <a href="create_user.php">Add Staff/Manager</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </header>

  <form method="post">
    <h2 style="text-align:center; color:#111;">Add Staff/Manager</h2>
    <?php if(!empty($msg)) echo "<p style='color:green; text-align:center;'>$msg</p>"; ?>
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Full Name</label>
    <input type="text" name="full_name">
    <label>Email</label>
    <input type="email" name="email">
    <label>Role</label>
    <select name="role">
      <option value="staff">Staff</option>
      <option value="manager">Manager</option>
    </select>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn">Create</button>
  </form>

  <footer>
    CARD RBI Operations Manager Module
  </footer>
</body>
</html>
