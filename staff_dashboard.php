<?php
require_once 'auth.php';
// Tinitiyak na ang user ay staff o may access
require_role(['staff', 'admin']); 
$user = current_user();

// Safe way to get the full_name, using null coalescing operator (??)
$user_full_name = $user['full_name'] ?? 'Staff User';
$user_role = $user['role'] ?? 'staff';
$user_id = $user['id'] ?? null; // Get user ID for specific notifications

require_once 'db.php'; // Ensure db.php is loaded for PDO object

// Fetch unverified payments count for sidebar/card badge
try {
    $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    $unverified_payments_count = 0;
}

// Fetch recent staff activity (last 5 logs)
try {
    $recent_activity = $pdo->query("
        SELECT
            a.created_at,
            a.action,
            u.full_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_activity = [];
}

// 🔒 Secure Notification Filtering for Staff
$notifications = [];
$unread_count = 0;

if ($user_id) {
    try {
        // Fetch only staff-specific or user-specific notifications
        $notif_stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE 
                (
                    target_user_id = :uid 
                    OR target_role = :role
                    OR (target_role = 'all' AND :role IN ('admin', 'manager', 'staff'))
                )
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $notif_stmt->execute([
            ':uid' => $user_id,
            ':role' => $user_role
        ]);
        $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count unread notifications securely
        $unread_count_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE 
                (
                    target_user_id = :uid 
                    OR target_role = :role
                    OR (target_role = 'all' AND :role IN ('admin', 'manager', 'staff'))
                )
                AND is_read = 0
        ");
        $unread_count_stmt->execute([
            ':uid' => $user_id,
            ':role' => $user_role
        ]);
        $unread_count = $unread_count_stmt->fetchColumn();

    } catch (PDOException $e) {
        $notifications = [];
        $unread_count = 0;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Base Styles and Layout */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }

        /* Sidebar */
        .sidebar {
            width: 230px;
            background: #0f172a;
            color: #fff;
            min-height: 100vh;
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .logo-box {
            display: flex;
            justify-content: left;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .logo-box img {
            height: 60px;
            width: auto;
            border-radius: 6px;
        }
        .sidebar a {
            color: #e2e8f0;
            text-decoration: none;
            padding: 12px 10px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: block;
            transition: 0.3s;
            font-weight: 500;
            position: relative;
        }
        .sidebar a:hover, .sidebar a.active { background: #1e293b; color: #fff; }

        /* Sidebar Notification Badge */
        .sidebar-link-badge .badge {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: #ef4444;
            color: white;
            padding: 2px 7px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }

        /* Main */
        .main {
            flex: 1;
            padding: 30px 40px;
        }
        
        /* HEADER AREA */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }

        header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e3a8a;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        /* ======================================================= */
        /* START: Notification Bell & Profile Styling (FIXED WITH FLEXBOX) */
        /* ======================================================= */

        /* --- Notification Bell Container --- */
        .notif-bell {
            position: relative;
            cursor: pointer;
            font-size: 20px;
            padding: 5px; 
            border-radius: 5px;
            transition: background 0.2s;
        }
        .notif-bell:hover {
            background: #f1f5f9;
        }
        .notif-bell i {
            color: #1e3a8a;
            font-size: 20px;
        }
        /* Notification Count Badge */
        .notif-bell .count { 
            background: #ef4444; color: #fff; font-size: 12px;
            border-radius: 50%; padding: 2px 6px; position: absolute; top: -6px; right: -8px;
            line-height: 1; min-width: 18px; text-align: center;
            box-shadow: 0 0 0 2px #f8fafc;
            font-weight: 700;
        }

        /* FIXED: Notification Dropdown Structure (Flexbox) */
        #notifDropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 300px;
            z-index: 1000;
            
            /* FLEXBOX PROPERTIES */
            display: flex; /* Default to flex, hidden via JS */
            flex-direction: column;
            max-height: 400px;
            overflow: hidden;
            
            /* Hidden by default, JS will set to 'flex' to show */
            display: none; 
            animation: fadeIn 0.2s ease-out;
        }

        #notifDropdown .notif-header {
            background: #f3f4f6;
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
            flex-shrink: 0; /* Important: prevents header from being compressed */
        }

        /* Scrollable Container */
        #notifDropdown .notif-scroll { 
            flex-grow: 1; /* Important: takes up remaining space */
            overflow-y: auto; /* Scroll only this section */
        }

        #notifDropdown .notif-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            color: #1e293b;
            display: block;
            transition: background 0.2s;
        }
        #notifDropdown .notif-item:hover {
             background: #f1f5f9;
        }
        #notifDropdown .notif-item:last-child {
            border-bottom: none;
        }

        #notifDropdown .notif-item strong {
            display: block;
            margin-bottom: 3px;
            font-size: 15px;
        }

        #notifDropdown .notif-item small {
            color: #6b7280;
            display: block;
        }
        #notifDropdown .notif-item em {
            display: block;
            padding: 15px;
            text-align: center;
            color: #6b7280;
        }


        /* --- Profile Dropdown --- */
        .profile-container {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #2563eb;
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
        }
        .profile-container:hover {
            background: #1d4ed8;
        }
        .profile-container i {
            color: #fff;
            font-size: 18px;
        }
        .dropdown-menu { 
            position: absolute;
            top: 40px;
            right: 0;
            background-color: #fff;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            padding: 10px 0;
            border: 1px solid #e2e8f0;
            display: none;
            animation: fadeIn 0.2s ease-out;
        }
        .dropdown-menu a {
            color: #334155;
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .dropdown-menu a:hover {
            background-color: #f8fafc;
        }
        .dropdown-menu a i {
            color: #64748b;
        }

        /* Animation for dropdowns */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ======================================================= */
        /* END: Notification Bell & Profile Styling */
        /* ======================================================= */


        /* Dashboard Cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 5px solid #2563eb;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .card h3 { color: #0f172a; font-size: 20px; margin-bottom: 15px; font-weight: 700; }
        .card p { color: #475569; font-size: 15px; margin-bottom: 20px; line-height: 1.5; }
        .card button {
            background: #2563eb;
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.3s, transform 0.1s;
        }
        .card button:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* Recent Activity Table Styling */
        .data-section {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-top: 30px;
            border-top: 5px solid #059669;
        }
        .data-section h3 {
            color: #059669;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .data-section table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .data-section th, .data-section td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-section th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            text-transform: uppercase;
        }
        .data-section tr:last-child td {
            border-bottom: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: unset; padding: 15px 20px; }
            .main { padding: 20px; }
            header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header-actions { align-self: flex-end; margin-top: 10px; width: 100%; justify-content: flex-end; }
            .cards { grid-template-columns: 1fr; }
            #notifDropdown, .dropdown-menu {
                left: unset;
                right: 0;
                width: 95%; 
                max-width: 350px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="staff_dashboard.php" class="active">🏠 Home</a>
        <a href="verify_payments.php" class="sidebar-link-badge">
            💰 Verify Payments
            <?php if ($unverified_payments_count > 0): ?>
                <span class="badge"><?= $unverified_payments_count ?></span>
            <?php endif; ?>
        </a>
        <a href="record_payment.php">📝 Record Payments</a>
        <a href="members.php">👥 Manage Members</a>
        <a href="upload_member_photo.php">📸 View Proofs</a>
        <a href="staff_ci_tasks.php">📋 My CI Tasks</a>

 
        </aside>

    <main class="main">
        <header>
            <div class="header-left">
                <h1>Welcome, <?= htmlspecialchars($user_full_name) ?> (<?= ucfirst($user_role) ?>)</h1>
            </div>

            <div class="header-actions">

                <div class="notif-bell" onclick="toggleNotif()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="count"><?= $unread_count ?></span>
                    <?php endif; ?>
                    
                    <div id="notifDropdown">
                        <div class="notif-header">
                             Notifications
                        </div>

                        <div class="notif-scroll">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <a href="view_notification.php?id=<?= $notif['id'] ?>" class="notif-item">
                                        <strong><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></strong>
                                        <small><?= htmlspecialchars(substr($notif['message'] ?? '', 0, 80) . (strlen($notif['message'] ?? '') > 80 ? '...' : '')) ?></small>
                                        <small><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notif-item"><em>No notifications found.</em></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-container" onclick="toggleProfileDropdown()">
                    <span><?= htmlspecialchars(explode(' ', $user_full_name)[0]) ?></span> <i class="fas fa-user-circle"></i>

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
        
        <div class="cards">
            <div class="card">
                <h3>💰 Verify Client Payments</h3>
                <p>Review and approve proofs of payment submitted by clients (GCash, online transfers).</p>
                <button onclick="location.href='verify_payments.php'">Review Queue (<?= $unverified_payments_count ?>)</button>
            </div>

            <div class="card">
                <h3>📝 Record Cash Payments</h3>
                <p>Manually log cash collections from clients on the field or center.</p>
                <button onclick="location.href='record_payment.php'">Open</button>
            </div>

            <div class="card">
                <h3>👥 Manage Members</h3>
                <p>Add new clients, update existing member details, and check loan records.</p>
                <button onclick="location.href='members.php'">Open</button>
            </div>

            <div class="card">
                <h3>📸 View Proofs</h3>
                <p>Monitor and submit business or loan progress photos monthly.</p>
                <button onclick="location.href='upload_member_photo.php'">Open</button>
            </div>
        </div>

        <div class="data-section">
            <h3>Recent System Activity (Last 5 Actions)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_activity): ?>
                        <?php foreach ($recent_activity as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))) ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['full_name'] ?? 'System User') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan='3'>No recent activity logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

<script>
    // Toggles the Notification Dropdown
    function toggleNotif() {
        const notif = document.getElementById("notifDropdown");
        const profile = document.getElementById("profileDropdown");
        
        // Close profile dropdown if open
        if (profile.style.display === "block") profile.style.display = "none";
        
        // Use 'flex' when showing the notification dropdown (matches CSS)
        notif.style.display = notif.style.display === "flex" ? "none" : "flex";
    }

    // Toggles the Profile Dropdown
    function toggleProfileDropdown() {
        const profile = document.getElementById("profileDropdown");
        const notif = document.getElementById("notifDropdown");
        
        // Close notification dropdown if open (check for 'flex' display)
        if (notif.style.display === "flex") notif.style.display = "none"; 
        
        // Toggle profile dropdown using 'block'
        profile.style.display = profile.style.display === "block" ? "none" : "block";
    }

    // Click outside to close both dropdowns
    document.addEventListener("click", function(e) {
        // Check if the click is outside both the bell container and profile container
        if (!e.target.closest(".notif-bell") && !e.target.closest(".profile-container")) {
            const notifDd = document.getElementById('notifDropdown');
            const profileDd = document.getElementById('profileDropdown');
            
            // Close both dropdowns
            if (notifDd) notifDd.style.display = 'none';
            if (profileDd) profileDd.style.display = 'none';
        }
    });
</script>
</body>
</html>