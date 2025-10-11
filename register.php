<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $age = (int)$_POST['age'];
    $password = $_POST['password'];

    // Age validation
    if ($age < 18) {
        $error = "You must be at least 18 years old to register.";
    } else {
        $role = 'client';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Start a transaction to ensure both user and member records are created or neither are.
            $pdo->beginTransaction();

            // 1. Insert into users table
            // Ensure your 'users' table has 'phone', 'address', 'age' columns
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, phone, address, age) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $role, $fullname, $email, $phone, $address, $age]);
            $user_id = $pdo->lastInsertId(); // Get the ID of the newly created user

            // 2. Insert into members table
            // This is the new part!
            // Ensure your 'members' table has 'user_id', 'name', 'address', 'phone', 'age', 'loan_amount', 'loan_start', 'status', 'created_by' columns.
            // For 'created_by', we can use NULL or a specific 'system' user_id if available.
            // For initial 'loan_amount', 'loan_start', 'status', use defaults.
            $stmtMember = $pdo->prepare("
                INSERT INTO members (user_id, name, address, phone, age, loan_amount, loan_start, status, created_by)
                VALUES (?, ?, ?, ?, ?, 0.00, CURDATE(), 'active', NULL)
            ");
            // Note: We use the full name from registration as the member's name.
            // loan_amount is 0, loan_start is today, status is active, created_by is NULL (system/self-registered)
            $stmtMember->execute([$user_id, $fullname, $address, $phone, $age]);

            // Commit the transaction if both inserts were successful
            $pdo->commit();

            $_SESSION['user_id'] = $user_id; // Use the actual user_id
            header("Location: client_dashboard.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback if any error occurs
            if ($e->getCode() === '23000') {
                $error = "Username already exists! Please choose a different one.";
            } else {
                $error = "An error occurred during registration. Please try again later. (" . $e->getMessage() . ")";
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register - CARD RBI</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
        --primary-color: #0056b3; /* Darker blue for primary actions */
        --secondary-color: #007bff; /* Bright blue for links/accents */
        --background-gradient: linear-gradient(135deg, #e0f2f7 0%, #cce7f5 100%); /* Light blueish gradient */
        --text-color: #333;
        --light-text-color: #666;
        --border-color: #e0e0e0;
        --input-focus-shadow: rgba(0, 123, 255, 0.25); /* Blue focus shadow */
        --error-bg: #ffe0e0;
        --error-text: #d32f2f;
        --box-shadow: 0px 10px 30px rgba(0,0,0,0.15);
    }

    body {
      margin: 0;
      padding: 0;
      /* Using a lighter gradient background for a fresh feel, you can still use your image if preferred. */
      background: var(--background-gradient); /* Or your card.jpg with an overlay */
      background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('card.jpg') no-repeat center center fixed; */
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
      box-sizing: border-box;
      width: 100%;
    }

    .register-box {
      background: #ffffff;
      padding: 40px;
      border-radius: 15px;
      width: 100%;
      max-width: 450px; /* Slightly wider */
      box-shadow: var(--box-shadow);
      text-align: center;
      position: relative;
      animation: fadeIn 0.8s ease-out forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .register-box img {
      width: 130px; /* Slightly larger logo */
      margin-bottom: 25px;
    }

    .register-box h2 {
      margin-bottom: 30px; /* More space below heading */
      color: var(--primary-color);
      font-weight: 700; /* Bolder heading */
      font-size: 28px;
    }

    .error-message-box {
        background-color: var(--error-bg);
        color: var(--error-text);
        border: 1px solid var(--error-text);
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 25px; /* More space below error */
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .error-message-box .fas { /* Font Awesome icon for error */
        color: var(--error-text);
        font-size: 16px;
    }

    .register-box label {
      display: block;
      text-align: left;
      margin-top: 18px; /* More space above labels */
      margin-bottom: 7px; /* More space below labels */
      font-weight: 500;
      color: var(--light-text-color);
      font-size: 14.5px;
    }

    .input-group {
        position: relative;
    }

    .register-box input {
      width: 100%;
      padding: 13px 15px; /* Slightly more padding */
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 15.5px;
      box-sizing: border-box;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .register-box input:focus {
      border-color: var(--secondary-color);
      box-shadow: 0 0 0 3px var(--input-focus-shadow);
      outline: none;
    }

    .register-box input[type="number"]::-webkit-outer-spin-button,
    .register-box input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .register-box input[type="number"] {
      -moz-appearance: textfield;
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 17px;
        transition: color 0.2s ease;
    }

    .toggle-password:hover {
        color: var(--primary-color);
    }

    .register-box button {
      margin-top: 35px; /* More space above button */
      width: 100%;
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 15px; /* More padding */
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      font-size: 18px;
      letter-spacing: 0.5px;
      transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
      box-shadow: 0 4px 10px rgba(0, 86, 179, 0.2);
    }
    .register-box button:hover {
      background-color: var(--secondary-color);
      transform: translateY(-3px);
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }
    .register-box button:active {
        transform: translateY(0);
        box-shadow: 0 2px 5px rgba(0, 86, 179, 0.2);
    }

    .register-box p {
      margin-top: 30px; /* More space above login link */
      font-size: 14.5px;
      color: var(--light-text-color);
    }

    .register-box a {
      color: var(--primary-color);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    .register-box a:hover {
      color: var(--secondary-color);
      text-decoration: underline;
    }

    .register-box input[name="age"]::placeholder {
      color: #999;
      font-style: italic;
    }

    @media (max-width: 500px) {
        .register-box {
            padding: 30px 25px;
            margin: 0 15px;
            max-width: 100%;
        }
        .register-box img {
            width: 110px;
        }
        .register-box h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .register-box input, .register-box button {
            padding: 12px;
            font-size: 15px;
        }
        .register-box label {
            margin-top: 15px;
        }
        .error-message-box {
            padding: 10px 12px;
            font-size: 13px;
        }
    }

  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="CARD RBI Logo">
      <h2>Create Your Client Account</h2>
      <?php if(!empty($error)): ?>
        <div class="error-message-box">
          <i class="fas fa-exclamation-circle"></i> <!-- Added Font Awesome icon -->
          <?php echo $error; ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <label for="username">Enter Your Username</label>
        <input type="text" id="username" name="username" placeholder="e.g., juan_delacruz" required>

        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" placeholder="Your complete name" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="e.g., you@example.com" required>

        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" pattern="[0-9]{11}" placeholder="e.g., 09xxxxxxxxx (11 digits)" title="Phone number must be 11 digits" required>

        <label for="address">Address</label>
        <input type="text" id="address" name="address" placeholder="House#, Street, Barangay, City" required>

        <label for="age">Age <span style="font-size: 0.85em; color: #999; font-weight: 400;">(Must be 18 or older)</span></label>
        <input type="number" id="age" name="age" min="18" placeholder="Enter your age" required>

        <label for="password">Password</label>
        <div class="input-group">
            <input type="password" id="password" name="password" placeholder="Create a strong password" required>
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
        </div>
        <button type="submit">Register Account</button>
      </form>
      <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
  </div>

  <script>
    // Password Toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function (e) {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Client-side age validation for better UX
    document.getElementById('age').addEventListener('change', function() {
        if (this.value < 18) {
            this.setCustomValidity('You must be at least 18 years old.');
        } else {
            this.setCustomValidity('');
        }
    });

  </script>
</body>
</html>