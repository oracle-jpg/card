<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Fetch user info (role is critical here)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// User Info for Page
$user_full_name = htmlspecialchars($user['full_name'] ?? 'User');
$user_username = htmlspecialchars($user['username'] ?? 'N/A');
$user_email = htmlspecialchars($user['email'] ?? 'N/A');
$user_role = strtolower($user['role'] ?? 'client'); // Make sure it's lowercase

// 2. Determine the correct dashboard URL based on the role
$dashboard_url = '';
switch ($user_role) {
    case 'admin':
        $dashboard_url = 'admin_dashboard.php';
        break;
    case 'manager':
        $dashboard_url = 'manager_dashboard.php';
        break;
    case 'staff':
        $dashboard_url = 'staff_dashboard.php';
        break;
    case 'client':
    default:
        $dashboard_url = 'client_dashboard.php';
        break;
}

// 3. Handle Success/Error message if redirected from change_password.php
$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Clear session message
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - <?= ucfirst($user_role) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
    .content-card {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        max-width: 450px; 
        margin: 30px auto;
    }
    .content-card h2 { font-size: 1.75rem; color: #1f2937; margin-bottom: 5px; }
    .subheading { color: #6b7280; font-size: 0.9rem; margin-bottom: 25px;}
    .user-info-section { padding: 15px; background: #f9fafb; border-radius: 6px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid #f3f4f6;}
    .form-group label { display: block; margin-top: 15px; font-weight: 600; color: #4b5563; font-size: 0.95rem; }
    .form-group input[type="password"] { width: 100%; padding: 12px; margin-top: 6px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
    .form-footer { display: flex; flex-direction: column; align-items: center; border-top: 1px solid #e5e7eb; padding-top: 25px; margin-top: 30px; }
    .logout-message { font-size: 0.85rem; color: #b91c1c; font-weight: 600; margin-bottom: 15px; }
    .submit-btn { background-color: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem; text-decoration: none; display: block; text-align: center; width: 100%; max-width: 250px; margin-top: 10px; }
    .submit-btn:hover { background-color: #b91c1c; }
    
    /* Message Box */
    .page-message { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: center; font-weight: 500; }
    .page-message.error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
    .page-message.success { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .back-to-dashboard-btn { background-color: #6b7280; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 20px;}
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="<?= $dashboard_url ?>" class="active-link">🏠 Dashboard</a>
        <a href="index.php?logout=1">🚪 Logout</a>
    </aside>

    <main class="main">
        <header>
            <h1>Change Password</h1>
        </header>

        <div class="content-card">

            <?php if ($message): ?>
            <div class="page-message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <p class="subheading">Update your password using the form below.</p>
            
            <div class="user-info-section">
                <p><strong>Name:</strong> <?= $user_full_name ?></p>
                <p><strong>Username:</strong> <?= $user_username ?></p>
                <p><strong>Role:</strong> <?= ucfirst($user_role) ?></p>
            </div>
            
            <form action="change_password.php" method="POST">
                
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required pattern=".{8,}" title="Password must be at least 8 characters long">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-footer">
                    <p class="logout-message"><i class="fas fa-exclamation-triangle"></i> WARNING: Changing your password will immediately log you out of the system for security reasons. You must log in again using your new password.</p>
                    <button type="submit" class="submit-btn">Update Password & Log Out</button>
                    
                    <a href="<?= $dashboard_url ?>" class="back-to-dashboard-btn">
                        <i class="fas fa-arrow-left"></i> Cancel and Go Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>