<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch notifications for current user or broadcast
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->execute([$user['id']]);
$notifications = $notif_stmt->fetchAll();

// Count unread notifications
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
$count_stmt->execute([$user['id']]);
$unread_count = $count_stmt->fetchColumn();

// Check role (admin only for this file)
if ($user['role'] !== 'admin') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}

// Fetch the 5 most recent pending loan applications
$pendingLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.created_at
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll();


// Notification Bell HTML (for inclusion) - Assuming notification_bell.php exists
$notification_bell_html = '';
if (file_exists('notification_bell.php')) {
    ob_start();
    include 'notification_bell.php';
    $notification_bell_html = ob_get_clean();
}

// Profile Info - No longer using full name here, just setting a variable for consistency.
$user_full_name = htmlspecialchars($user['full_name'] ?? 'Admin User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operations Manager Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base Styles (Minimal here, relying on style.css) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }

        /* Main */
        .main { flex: 1; margin-left: 230px; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }

        /* Table Styles (Minimal here, relying on style.css) */
        table { width: 100%; border-collapse: collapse; }
        table th, table td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f1f5f9; }
        
        /* Dropdown Styles (Added for this file) */
        .profile-container { position: relative; cursor: pointer; }
        .dropdown-menu {
            position: absolute;
            top: 100%; 
            right: 0;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1500;
            min-width: 180px;
            padding: 5px 0;
            display: none; /* Default hidden */
        }
        .dropdown-menu a {
            display: flex; 
            align-items: center;
            padding: 10px 15px;
            text-decoration: none;
            color: #374151;
            font-size: 0.9rem;
        }
        .dropdown-menu a:hover {
            background: #f3f4f6;
        }
        .dropdown-menu a i {
            margin-right: 8px;
            width: 16px;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="admin_dashboard.php" class="active-link">🏠 Home</a>
        <a href="manage_members.php">👥 Manage Members</a>
        <a href="manage_loans.php">💼 Manage Loans</a>
        <a href="record_payment.php">💰 Record Payments</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="create_user.php">➕ Create Personnel</a>
    </aside>

    <main class="main admin-dashboard">
        <header>
            <h1>Welcome to the Admin Dashboard</h1>
            
            <div class="header-actions"> 
                
                <div class="notif-container" onclick="toggleDropdown()">
                    <?= $notification_bell_html ?>
                </div>

                <div class="profile-container" onclick="toggleProfileDropdown()">
                    <div class="profile-info">
                        <span>Admin</span> 
                        <i class="fas fa-user-circle fa-lg"></i>
                    </div>

                    <div id="profileDropdown" class="dropdown-menu">
                        <a href="change_password_page.php"> 
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <a href="logout.php"> 
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-grid">
            
            <div class="card-form">
                <h3>📊 System Overview</h3>
                <table>
                    <tr><th>Category</th><th>Total</th></tr>
                    <tr><td>Total Clients</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Staff</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Managers</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Loans</td><td><?= $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Payments</td><td><?= $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(); ?></td></tr>
                </table>
            </div>

            <div class="card-form">
                <h3>⏳ Pending Loan Applications</h3>
                <table>
                    <tr>
                        <th>Loan ID</th>
                        <th>Client Name</th>
                        <th>Amount</th>
                        <th>Requested Date</th>
                        <th>Action</th>
                    </tr>
                    <?php if ($pendingLoans): ?>
                        <?php foreach ($pendingLoans as $loan): ?>
                        <tr>
                            <td><?= $loan['id'] ?></td>
                            <td><?= htmlspecialchars($loan['member_name']) ?></td>
                            <td>₱<?= number_format($loan['amount'], 2) ?></td>
                            <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                            <td>
                                <a href="manage_loans.php?loan_id=<?= $loan['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:600;">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='5'>No pending loan applications. 🎉</td></tr>
                    <?php endif; ?>
                </table>
            </div>
            
        </div> 
        <div class="card full-width-card">
            <h3>🧾 Recent System Activity</h3>
            <table>
                <tr><th>Date</th><th>Action</th><th>User</th></tr>
                <?php
                $logs = $pdo->query("
                    SELECT a.created_at, a.action, u.full_name 
                    FROM audit_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.created_at DESC 
                    LIMIT 5
                ")->fetchAll();

                if ($logs) {
                    foreach ($logs as $log) {
                        echo "<tr>
                                    <td>{$log['created_at']}</td>
                                    <td>{$log['action']}</td>
                                    <td>{$log['full_name']}</td>
                                </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No recent activity.</td></tr>";
                }
                ?>
            </table>
        </div>
    </main>

    <script>
    function toggleDropdown() {
        const dd = document.getElementById('notifDropdown');
        const profileDd = document.getElementById('profileDropdown');

        if (profileDd && profileDd.style.display === 'block') {
             profileDd.style.display = 'none';
        }

        if (dd) {
            dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
        }
    }

    function toggleProfileDropdown() {
        const dd = document.getElementById('profileDropdown');
        const notifDd = document.getElementById('notifDropdown');

        if (notifDd && notifDd.style.display === 'block') {
            notifDd.style.display = 'none';
        }

        dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
    }

    // Modal Logic functions are removed.
    // openChangePassModal() and closeChangePassModal() are removed.
    
    // AJAX logic is removed since we are linking to a page now.
    // document.getElementById('changePasswordForm').addEventListener('submit', ...) is removed.

    // Global Click Listener (Para sa pag-sara ng dropdowns)
    window.onclick = function(e) {
        // Close Notification Dropdown ONLY if the click is outside the container
        if (!e.target.closest('.notif-container')) {
            const notifDd = document.getElementById('notifDropdown');
            if (notifDd) notifDd.style.display = 'none';
        }
        
        // Close Profile Dropdown ONLY if the click is outside the container
        if (!e.target.closest('.profile-container')) {
            const profileDd = document.getElementById('profileDropdown');
            if (profileDd) profileDd.style.display = 'none';
        }

        // Modal closing logic is removed.
    }
    </script>
</body>
</html>