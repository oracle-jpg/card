<?php
// Tinitiyak na ang user ay naka-login at may 'admin' role
require_once 'auth.php';
require_role(['admin']);
$user = current_user();

$msg = '';
$user_full_name = $user['full_name'] ?? 'Admin User';
// Dito inayos: Tiyakin na ang value ay 'System Administrator' at hindi '1' (na galing sa boolean true)
$user_role_display = ($user['role'] === 'admin') ? 'System Administrator' : ucfirst($user['role']);
$dashboard_link = 'admin_dashboard.php'; // Link pabalik sa Admin Dashboard

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password) || empty($fullname) || empty($email)) {
        $msg = "Lahat ng field ay kailangan.";
    } elseif (!in_array($role, ['staff', 'manager'])) {
        $msg = "Di-wastong role. Staff/Manager lang ang pinapayagan.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            // Check if username already exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            if ($check_stmt->fetchColumn() > 0) {
                 $msg = "Error: Ang username na '$username' ay ginagamit na.";
            } else {
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $role, $fullname, $email]);
                $msg = "Success: User (Role: " . ucfirst($role) . ") ay matagumpay na nagawa.";
            }
        } catch (PDOException $e) {
            $msg = "Database Error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff/Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; }

        /* Sidebar (Admin Panel Styling) */
        .sidebar {
            width: 230px;
            background: #0f172a; /* Darker sidebar for contrast */
            color: #fff;
            min-height: 100vh;
            padding: 20px;
            position: fixed;
            z-index: 10;
        }
        .logo-box {
            display: flex;
            justify-content: left;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .logo-box img {
            height: 60px; 
            width: auto;
            border-radius: 6px; 
        }

        .sidebar a {
            color: #e0f2fe;
            text-decoration: none;
            padding: 12px 10px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: block;
            transition: 0.3s;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active { 
            background: #1e293b; 
            color: #fff; 
            font-weight: 600;
        }
        .logout {
            margin-top: auto; 
            background: #ef4444;
            color: #fff;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
            font-weight: 600;
            position: absolute;
            bottom: 20px;
            width: calc(100% - 40px);
        }
        .logout:hover { background: #dc2626; }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: 230px;
            padding: 30px;
            display: flex; 
            flex-direction: column;
        }
        header {
            width: 100%;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e3a8a;
        }
        .profile {
            background: #facc15;
            color: #1e3a8a;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-transform: capitalize;
        }
        
        /* Form Card Styling - Ito ang na-center */
        .form-card {
            max-width: 450px;
            width: 100%;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-top: 5px solid #1e3a8a;
            margin: 0 auto; 
        }
        .form-card h2 {
            font-size: 22px;
            text-align: center;
            color: #1e3a8a;
            margin-bottom: 20px;
            font-weight: 700;
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        button[type="submit"] {
            width: 100%;
            background: #10b981; /* Green submit button */
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 25px;
            transition: background 0.3s;
        }
        button[type="submit"]:hover {
            background: #059669;
        }
        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
        }
        .message.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        .message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Responsive adjustments for mobile view */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { 
                width: 100%; 
                position: relative; 
                min-height: unset; 
                padding: 15px 20px; 
            }
            .sidebar h2 { display: none; } 
            .logo-box { padding: 0; margin-bottom: 0; }
            .logo-box img { height: 40px; margin-bottom: 10px; }

            .sidebar nav { 
                display: flex; 
                width: 100%; 
                justify-content: space-around;
            }
            .sidebar a {
                padding: 8px 10px;
                margin: 0 5px;
                flex-grow: 1;
                text-align: center;
            }
            .logout {
                position: relative; 
                width: 100%;
                margin-top: 10px;
                bottom: 0;
            }
            
            .main { 
                padding: 20px;
                margin-left: 0; 
            }
            header { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 10px; 
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar - Navigation Bar -->
    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <nav>
            <a href="<?= htmlspecialchars($dashboard_link) ?>">🏠 Home</a>
            <a href="create_user.php" class="active">👤 Create Personnel</a>
        </nav>  
    </aside>

    <!-- Main Content Area -->
    <main class="main">
        <header>
            <h1>Operations Manager Panel</h1>
            <!-- Tanging Buong Pangalan na lang ang ipapakita (inalis ang role sa parenthesis) -->
            <div class="profile">👤 <?= htmlspecialchars($user_full_name) ?></div>
        </header>

        <!-- Form Card for User Creation - Naka-center na ngayon -->
        <div class="form-card">
            <h2>Create Personnel</h2>
            
            <?php if(!empty($msg)): ?>
                <div class="message <?= strpos($msg, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <label for="username">Enter username</label>
                <input type="text" id="username" name="username" required>
                
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required>
                
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                </select>
                
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit">Create</button>
            </form>
        </div>
        
    </main>

</body>
</html>
