<?php
session_start();
require 'db.php'; // Make sure db.php establishes $pdo

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check role (admin only)
if ($user['role'] !== 'admin') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}

$user_role = $user['role'] ?? 'admin'; // Fallback for safety

// Fetch notifications for current user, broadcast, or admin role (Limit 15 for scroll demo)
$notif_stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE target_role = 'all' OR target_role = ? OR target_user_id = ?
    ORDER BY created_at DESC 
    LIMIT 15
");
$notif_stmt->execute([$user_role, $user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE (target_role = 'all' OR target_role = ? OR target_user_id = ?) AND is_read = 0
");
$count_stmt->execute([$user_role, $user_id]);
$unread_count = $count_stmt->fetchColumn();

// Helper function for fetching count
function get_count($pdo, $sql) {
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        // Log the error for debugging, but return 0 to prevent breaking the page
        error_log("Database error in get_count: " . $e->getMessage());
        return 0;
    }
}

// Fetch system overview counts
$total_clients = get_count($pdo, "SELECT COUNT(*) FROM users WHERE role='client'");
$total_staff = get_count($pdo, "SELECT COUNT(*) FROM users WHERE role='staff'");
$total_managers = get_count($pdo, "SELECT COUNT(*) FROM users WHERE role='manager'");
$total_loans = get_count($pdo, "SELECT COUNT(*) FROM loans");
$total_payments = get_count($pdo, "SELECT COUNT(*) FROM payments");

// Fetch pending loans - This already joins with 'members' to get 'member_name'
$pendingLoans = $pdo->query("
    SELECT l.id, m.name AS member_name, l.amount, l.created_at
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent activity logs - This already joins with 'users' to get 'full_name'
$logs = $pdo->query("
    SELECT a.created_at, a.action, u.full_name 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base Styles (Simplified for this context, ensure your full style.css is working) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }
        .sidebar { /* Placeholder styles */ width: 230px; background: #0f172a; color: #fff; min-height: 100vh; position: fixed; }
        .main { flex: 1; margin-left: 230px; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; }
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        .card-form { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 5px solid #059669; }
        
        .full-width-card { 
            grid-column: 1 / -1; 
        }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        table th, table td { text-align: left; padding: 12px 15px; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f1f5f9; color: #334155; font-weight: 600; text-transform: uppercase; }

        /* --- Notification Bell Styling --- */
        .header-actions {
            display: flex; align-items: center; gap: 15px; position: relative;
        }
        .notif-container {
            position: relative; cursor: pointer; font-size: 20px;
        }
        .notif-count {
            position: absolute; top: -6px; right: -8px; background: #ef4444; color: white;
            font-size: 12px; font-weight: 700; padding: 2px 6px; border-radius: 50%;
            line-height: 1; min-width: 18px; text-align: center; box-shadow: 0 0 0 2px #f8fafc;
        }
        .notif-dropdown {
            position: absolute; top: 40px; right: 0; width: 350px; background: white;
            border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 2000; display: none; flex-direction: column; max-height: 450px; overflow: hidden; 
        }
        .notif-dropdown h4 {
            font-size: 0.95rem; font-weight: 600; color: #374151; padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb; background: #f3f4f6; flex-shrink: 0; 
        }
        .notif-scroll-area {
            flex-grow: 1; overflow-y: auto; max-height: 350px; 
        }
        .notif-item-link {
            display: block; text-decoration: none; color: #374151; padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb; transition: background 0.2s;
        }
        .notif-item-link:hover {
            background: #f1f5f9;
        }
        .notif-unread-item {
            background-color: #eff6ff; border-left: 4px solid #3b82f6; padding-left: 11px;
        }
        .notif-item-link strong {
            display: block; font-size: 0.9rem; margin-bottom: 3px;
        }
        .notif-item-link small {
            display: block; color:#6b7280; font-size: 0.8rem;
        }
        .notif-dropdown a.create-btn {
            flex-shrink: 0; display: block; text-align: center; padding: 10px;
            background: #2563eb; color: white; text-decoration: none; font-weight: 600;
            border-top: 1px solid #1d4ed8; border-radius: 0 0 8px 8px;
        }
        /* Profile Dropdown Styles */
        .dropdown-menu { 
            display: none; 
            position: absolute; 
            top: 40px; 
            right: 0; 
            background: white; 
            border: 1px solid #e5e7eb; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 180px;
            padding: 5px 0;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #374151;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .dropdown-menu a:hover {
            background-color: #f1f5f9;
        }
        .dropdown-menu a i {
            margin-right: 10px;
            color: #6b7280;
        }
        .profile-container {
            position: relative;
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 8px;
            font-weight: 600;
            color: #334155;
        }
        .profile-container i {
            font-size: 24px; /* Larger icon for visibility */
            color: #6b7280;
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
        <a href="generate_reports.php">📊 Reports</a>
        <a href="create_user.php">➕ Create Personnel</a>
        <a href="admin_ci_list.php">📝 Credit Investigations</a>

    </aside>

    <main class="main admin-dashboard">
        <header>
            <h1>Welcome, Admin!</h1>
            <div class="header-actions"> 
                
                <div class="notif-container" onclick="toggleNotifDropdown()">
                    <i class="fas fa-bell notif-icon"></i>
                    <?php if ($unread_count > 0): ?>
                        <span id="unreadCountBadge" class="notif-count"><?= $unread_count ?></span>
                    <?php endif; ?>

                    <div id="notifDropdown" class="notif-dropdown">
                        <h4>Notifications</h4>
                        
                        <div id="notifScrollArea" class="notif-scroll-area">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): 
                                    $is_unread_class = ($notif['is_read'] == 0) ? 'notif-unread-item' : '';
                                ?>
                                    <a href="view_notification.php?id=<?= $notif['id'] ?>" 
                                       class="notif-item-link <?= $is_unread_class ?>" 
                                       id="notif-<?= $notif['id'] ?>">
                                        
                                        <strong><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></strong>
                                        <small><?= htmlspecialchars(substr($notif['message'] ?? 'No message', 0, 50)) . (strlen($notif['message'] ?? '') > 50 ? '...' : '') ?></small>
                                        <small><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></small>
                                        
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notif-item-link" style="text-align:center; padding: 15px;">No new notifications.</div>
                            <?php endif; ?>
                        </div>

                        <a href="create_announcement.php" class="create-btn">+ Create Announcement</a>
                    </div>
                </div>

                <div class="profile-container" onclick="toggleProfileDropdown()">
                    <span><?= htmlspecialchars(explode(' ', $user['full_name'] ?? 'Admin')[0]) ?></span> 
                    <i class="fas fa-user-circle fa-lg"></i>
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
                    <tr><td>Total Clients</td><td><?= $total_clients ?></td></tr>
                    <tr><td>Total Staff</td><td><?= $total_staff ?></td></tr>
                    <tr><td>Total Managers</td><td><?= $total_managers ?></td></tr>
                    <tr><td>Total Loans</td><td><?= $total_loans ?></td></tr>
                    <tr><td>Total Payments</td><td><?= $total_payments ?></td></tr>
                </table>
            </div>

            <div class="card-form">
                <h3>⏳ Pending Loan Applications</h3>
                <table>
                    <tr>
                        <th>Member Name</th> 
                        <th>Amount</th>
                        <th>Requested Date</th>
                        <th>Action</th>
                    </tr>
                    <?php if ($pendingLoans): ?>
                        <?php foreach ($pendingLoans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['member_name']) ?></td> 
                            <td>₱<?= number_format($loan['amount'], 2) ?></td>
                            <td><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                            <td>
                                <a href="manage_loans.php?loan_id=<?= $loan['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:600;">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='4' style="text-align:center; padding: 20px;">No pending loan applications. 🎉</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div> 

        <div class="card full-width-card card-form">
            <h3>🧾 Recent System Activity</h3>
            <table>
                <tr><th>Date</th><th>Action</th><th>User</th></tr>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))) ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= htmlspecialchars($log['full_name'] ?? 'System User') ?></td> 
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan='3' style="text-align:center; padding: 20px;">No recent activity.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>

    <script>
    function toggleNotifDropdown() {
        const notifDd = document.getElementById('notifDropdown');
        const profileDd = document.getElementById('profileDropdown');
        
        if (profileDd && profileDd.style.display === 'block') {
            profileDd.style.display = 'none';
        }
        
        notifDd.style.display = (notifDd.style.display === 'flex') ? 'none' : 'flex'; 
    }

    function toggleProfileDropdown() {
        const profileDd = document.getElementById('profileDropdown');
        const notifDd = document.getElementById('notifDropdown');
        
        if (notifDd && notifDd.style.display === 'flex') notifDd.style.display = 'none';
        
        profileDd.style.display = (profileDd.style.display === 'block') ? 'none' : 'block';
    }

    // Click outside to close both dropdowns
    window.onclick = function(e) {
        if (!e.target.closest('.notif-container')) {
            const notifDd = document.getElementById('notifDropdown');
            if (notifDd && notifDd.style.display === 'flex') notifDd.style.display = 'none';
        }
        if (!e.target.closest('.profile-container')) {
            const profileDd = document.getElementById('profileDropdown');
            if (profileDd && profileDd.style.display === 'block') profileDd.style.display = 'none';
        }
    }
    </script>
</body>
</html>