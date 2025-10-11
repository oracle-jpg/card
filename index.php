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
        $error = "Username or Password not found. Please try again.";
    } elseif (password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        // Ensure 'index.php' is your actual homepage. If not, change 'index.php'
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - CARD RBI Microfinance</title>
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      margin: 0;
      padding: 0;
      /* Using your local card.jpg image. Make sure it's in the same directory or provide the correct path. */
      /* Added a subtle dark overlay (linear-gradient) for better text contrast if your image is very bright. */
      /* Increased background size for better fill */
      background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('card.jpg') no-repeat center center fixed;
      background-size: cover; /* Ensure it covers the whole screen */
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #333;
    }

    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-box {
      background: rgba(255,255,255,0.98);
      padding: 25px 40px; /* Reduced vertical padding (height), increased horizontal padding (width) */
      border-radius: 12px;
      width: 400px; /* Increased width */
      max-width: 90%; /* Ensure responsiveness on smaller screens */
      box-shadow: 0px 8px 25px rgba(0,0,0,0.3);
      text-align: center;
      animation: fadeIn 0.8s ease-out forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .login-box img {
      width: 180px;
      margin-bottom: 25px;
    }

    .login-box h2 {
      margin-bottom: 25px;
      color: #0033A0;
      font-size: 28px;
    }

    .login-box label {
      display: block;
      text-align: left;
      margin-top: 15px;
      margin-bottom: 5px;
      font-weight: bold;
      color: #555;
      font-size: 15px;
    }

    .input-group {
        position: relative;
        margin-bottom: 15px;
    }

    .login-box input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      font-size: 16px;
      padding-right: 40px; /* Make space for the eye icon */
      box-sizing: border-box;
    }

    .login-box input:focus {
      border-color: #0033A0;
      box-shadow: 0 0 0 3px rgba(0, 51, 160, 0.2);
      outline: none;
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 18px;
        transition: color 0.2s ease;
    }

    .toggle-password:hover {
        color: #0033A0;
    }


    .login-box button {
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
    .login-box button:hover {
      background: #FFD700;
      color: #0033A0;
      transform: translateY(-2px);
    }

    .login-box .home-button {
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

    .login-box .home-button:hover {
      background: #cbd5e1;
      color: #1a202c;
      transform: translateY(-2px);
    }

    .error-message {
        background-color: #ffebeb;
        color: #d8000c;
        border: 1px solid #d8000c;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 20px;
        text-align: left;
        font-size: 14px;
    }

/* New styles for the specific layout requested */
    .links-group {
        display: flex;
        justify-content: space-between; /* Pushes the left block and right link apart */
        align-items: flex-start; /* Aligns items to the top if they have different heights */
        margin-top: 18px;
        padding: 0 5px;
        flex-wrap: wrap; /* Allows items to wrap if screen is too small, though unlikely for this content */
    }

    .links-group > div { /* This targets the left block containing "Don't have an account?" and "Register here" */
        display: flex;
        flex-direction: column; /* Stacks its children vertically */
        align-items: flex-start; /* Aligns text/link to the left */
        gap: 2px; /* Small gap between the <p> and <a> */
        font-size: 14px;
        flex-basis: auto; /* Allow content to dictate its width */
    }

    .links-group p {
        margin: 0; /* Remove default paragraph margins */
        color: #666;
        font-size: 14px; /* Ensure consistency */
    }

    .links-group a {
        color: #0033A0;
        font-weight: bold;
        text-decoration: none;
        transition: color 0.3s ease;
        font-size: 14px; /* Ensure consistency */
    }
    .links-group a:hover {
        color: #FFD700;
        text-decoration: underline;
    }

    .links-group .forgot-password-link {
        margin-top: auto; /* Pushes this link to the bottom if the left group is taller */
        /* If you want it aligned with "Register here" for example, adjust this */
        /* For your specific request, it aligns with "Register here" by default due to flex-start + column on the left */
        align-self: flex-end; /* Align the 'Forgot password?' link to the bottom of its line */
    }
  </style>
</head>
<body>

  <div class="login-container">
    <div class="login-box">
      <!-- **IMPORTANT: Update this src attribute with the path to your desired logo image** -->
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo - Ito ang bangko natin.">
      <h2>Log In to Your Account</h2>
      <?php if(!empty($error)) echo "<p class='error-message'>$error</p>"; ?>
      <form method="post">
        <label for="username">Username</label>
        <div class="input-group">
            <input type="text" id="username" name="username" placeholder="e.g., juan_delacruz" required>
        </div>

        <label for="password">Password</label>
        <div class="input-group">
            <input type="password" id="password" name="password" placeholder="••••••••" required>
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <button type="submit">Secure Login</button>
      </form>

      <div class="links-group">
          <!-- This div groups "Don't have an account?" and the "Register here" link -->
          <div>
              <p>Don’t have an account?</p>
              <!-- Moved "Register here" outside the <p> for better styling control -->
              <a href="register.php">Register here</a>
          </div>
          <!-- This is the "Forgot password?" link on the right -->
          <a href="forgot_password.php" class="forgot-password-link">Forgot password?</a>
      </div>

      <a href="home_page.php" class="home-button">Back to Home</a> <!-- Ensure homepage.php is correct -->
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // toggle the eye / eye-slash icon
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>