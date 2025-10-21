<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Role check (manager only)
if ($user['role'] !== 'manager') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}
if (isset($_GET['approve_member'])) {
    $member_id = $_GET['approve_member'];

    $update = $pdo->prepare("UPDATE members SET ci_status = ? WHERE id = ?");
    $update->execute(['approved', $member_id]);

    echo "<script>alert('Member approved!');</script>";
}


$user_full_name = htmlspecialchars($user['full_name'] ?? 'Manager User');
$user_username = htmlspecialchars($user['username'] ?? 'N/A');
$user_email = htmlspecialchars($user['email'] ?? 'N/A');

// Fetch notifications for the current user's role or 'all'
// Make sure your notifications table has 'title' column for consistent display
$current_user_role = $user['role']; // Get the actual role for fetching
$notif_stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE target_role = 'all' OR target_role = ? OR target_user_id = ?
    ORDER BY created_at DESC LIMIT 5
");
$notif_stmt->execute([$current_user_role, $user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications (assuming you have an 'is_read' column)
$unread_count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE (target_role = 'all' OR target_role = ? OR target_user_id = ?) AND is_read = 0
");
$unread_count_stmt->execute([$current_user_role, $user_id]);
$unread_count = $unread_count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f3f4f6;
        margin: 0;
        display: flex; /* Added for sidebar/main layout */
    }
    .main {
        flex: 1;
        margin-left: 230px; /* Assuming your sidebar is 230px wide */
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 20px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative; /* Ensure proper positioning for dropdowns */
    }

    /* --- Notification Bell --- */
    .notif-bell {
        position: relative;
        cursor: pointer;
        font-size: 20px;
        color: #374151; /* Default bell color */
    }

    /* Notification Count Badge */
    .notif-count {
        position: absolute;
        top: -5px;
        right: -8px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 5px;
        border-radius: 50%;
        line-height: 1;
        min-width: 18px;
        text-align: center;
        box-shadow: 0 0 0 2px #f9fafb;
    }

    /* Notification Dropdown Structure */
    #notifDropdown {
        display: none; /* THIS IS THE CRITICAL FIX: Ensure it's hidden by default */
        position: absolute;
        right: 0;
        top: 40px; /* Adjust based on header height if needed */
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        width: 300px;
        z-index: 1000;

        flex-direction: column; /* Ensure it's a column flex container when visible */
        max-height: 400px;
        overflow: hidden; /* For scrollable content */
    }

    #notifDropdown .notif-header {
        background: #f3f4f6;
        padding: 10px 15px;
        border-bottom: 1px solid #e5e7eb;
        font-weight: 600;
        color: #1f2937;
        flex-shrink: 0; /* Prevent header from shrinking */
        display: flex; /* To align title and mark all as read */
        justify-content: space-between;
        align-items: center;
    }
    #notifDropdown .notif-header .mark-all-read {
        font-size: 0.8rem;
        color: #2563eb;
        text-decoration: none;
    }
    #notifDropdown .notif-header .mark-all-read:hover {
        text-decoration: underline;
    }


    /* Scrollable Notification Items */
    #notifDropdown .notif-scroll-area {
        max-height: 300px; /* Max height for the scroll area */
        overflow-y: auto; /* Enable scrolling */
        flex-grow: 1; /* Allow this area to grow and take available space */
    }

    /* Make the entire notification item clickable and style it */
    .notif-item-link {
        display: block; /* Make the whole area clickable */
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
        text-decoration: none; /* Remove underline */
        color: inherit; /* Inherit text color */
    }
    .notif-item-link:last-child {
        border-bottom: none; /* No border for the last item */
    }
    .notif-item-link:hover {
        background: #f9fafb;
    }
    .notif-item-link.unread {
        background-color: #eff6ff; /* Light blue background for unread */
        font-weight: 500;
    }

    .notif-item-link strong {
        display: block;
        margin-bottom: 3px;
        font-size: 0.9rem;
        color: #1f2937;
    }

    .notif-item-link small {
        color: #6b7280;
        font-size: 0.75rem;
    }
    #notifDropdown .notif-item-link em { /* For "No notifications found" */
        color: #6b7280;
        font-size: 0.9rem;
        display: block;
        text-align: center;
        padding: 15px 0;
    }

    /* --- Profile Dropdown --- */
    .profile-container {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 6px;
        transition: background 0.2s;
        color: #374151; /* Default profile text color */
    }
    .profile-container:hover {
        background: #e5e7eb;
    }

    .profile-container i {
        font-size: 22px;
    }

    .dropdown-menu {
        position: absolute;
        top: 40px; /* Adjust based on header height if needed */
        right: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 180px;
        padding: 5px 0;
        display: none; /* THIS IS CORRECT: Hidden by default */
        z-index: 1000;
    }

    .dropdown-menu a {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        color: #374151;
        font-size: 0.9rem;
        text-decoration: none;
    }

    .dropdown-menu a:hover {
        background: #f3f4f6;
    }

    .dropdown-menu a i {
        margin-right: 8px;
        width: 16px;
    }

    /* --- Dashboard Content Styles --- */
    h1 {
        font-size: 1.8rem;
        color: #1f2937;
    }
    h3 {
        font-size: 1.25rem;
        color: #1f2937;
        margin-bottom: 15px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    th, td {
        padding: 8px 12px;
        border: 1px solid #e5e7eb;
        text-align: left;
        font-size: 0.9rem;
        color: #374151;
    }

    th {
        background: #f9fafb;
        font-weight: 600;
        color: #1f2937;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* More flexible grid */
        gap: 20px;
        padding: 20px;
    }

    .card-form {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }

    .full-width-card {
        grid-column: 1 / -1; /* Spans all columns */
    }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="manager_dashboard.php" class="active-link">🏠 Home</a>
        <a href="staff_performance.php">👥 Staff Performance</a>
        <a href="loan_overview.php">💼 Loans Overview</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="manager_loan_approval.php">✅ Loan Approvals</a>
        <a href="manager_ci_review.php">✅ Review CI Reports</a>

       
    </aside>

    <main class="main manager-dashboard">
        <header>
            <h1>Welcome, <?= $user_full_name ?> (Manager)</h1>

            <div class="header-actions">

                <div class="notif-bell" onclick="toggleNotif(event)">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-count"><?= $unread_count ?></span>
                    <?php endif; ?>

                    <div id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifications</span>
                            <!-- Link to mark all notifications as read 
                            <a href="mark_all_notifications_read.php?role=<?= htmlspecialchars($current_user_role) ?>&user_id=<?= htmlspecialchars($user_id) ?>" class="mark-all-read">Mark all as read</a>-->
                        </div>

                        <div class="notif-scroll-area">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <!-- Each notification is now a clickable link -->
                                    <a href="view_notification.php?id=<?= htmlspecialchars($notif['id']) ?>" class="notif-item-link <?= ($notif['is_read'] == 0) ? 'unread' : '' ?>">
                                        <strong><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></strong>
                                        <small><?= htmlspecialchars($notif['message'] ?? '') ?></small><br>
                                        <small><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notif-item-link"><em>No notifications found.</em></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-container" onclick="toggleProfileDropdown(event)">
                    <span>Manager</span>
                    <i class="fas fa-user-circle"></i>

                    <div id="profileDropdown" class="dropdown-menu">
                        <a href="change_password_page.php"><i class="fas fa-key"></i> Change Password</a>
                        <a href="index.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>

            </div>
        </header>

        <div class="dashboard-grid">
            <div class="card-form">
                <h3>📈 Branch Overview</h3>
                <table>
                    <tr><th>Category</th><th>Count</th></tr>
                    <tr><td>Total Staff</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Clients</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Loans</td><td><?= $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(); ?></td></tr>
                    <tr><td>Total Payments</td><td><?= $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(); ?></td></tr>
                </table>
            </div>

            <div class="card-form">
                <h3>👥 Staff Performance Summary</h3>
                <table>
                    <tr><th>Staff Name</th><th>Payments Collected</th><th>Last Activity</th></tr>
                    <?php
                    $staffPerf = $pdo->query("
                        SELECT u.full_name, COUNT(p.id) AS total_collections, MAX(p.created_at) AS last_activity
                        FROM users u
                        LEFT JOIN payments p ON p.collected_by = u.id
                        WHERE u.role = 'staff'
                        GROUP BY u.id, u.full_name
                        ORDER BY total_collections DESC
                    ")->fetchAll();

                    if ($staffPerf) {
                        foreach ($staffPerf as $s) {
                            echo "<tr>
                                    <td>" . htmlspecialchars($s['full_name']) . "</td>
                                    <td>{$s['total_collections']}</td>
                                    <td>" . ($s['last_activity'] ? date('M d, Y', strtotime($s['last_activity'])) : 'N/A') . "</td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>No staff performance data available.</td></tr>";
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="card full-width-card card-form">
            <h3>🧾 Recent Activity</h3>
            <table>
                <tr><th>Date</th><th>Action</th><th>User</th></tr>
                <?php
                // Pre-prepare statements for efficiency
                $stmt_member_name = $pdo->prepare("SELECT name FROM members WHERE id = ?");
                $stmt_loan_info = $pdo->prepare("SELECT amount, status FROM loans WHERE id = ?"); // Fetch loan amount and status

                $logs = $pdo->query("
                    SELECT a.created_at, a.action, u.full_name as user_full_name
                    FROM audit_logs a
                    LEFT JOIN users u ON a.user_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT 5
                ")->fetchAll();

                if ($logs) {
                    foreach ($logs as $log) {
                        $original_action = $log['action'];
                        $display_action = htmlspecialchars($original_action); // Default to original, escaped

                        // --- Logic for "Released Loan ID X to Member Y" ---
                        $pattern_released_loan = '/^Released Loan ID (\d+) to Member (\d+)$/';
                        if (preg_match($pattern_released_loan, $original_action, $matches)) {
                            $extracted_loan_id = $matches[1];
                            $extracted_member_id = $matches[2];

                            $member_display_name = 'Unknown Member';
                            $loan_amount = 'N/A';
                            $loan_status = 'N/A';

                            // Get Member Name
                            $stmt_member_name->execute([$extracted_member_id]);
                            $member_info = $stmt_member_name->fetch(PDO::FETCH_ASSOC);
                            if ($member_info && !empty($member_info['name'])) {
                                $member_display_name = htmlspecialchars($member_info['name']);
                            }

                            // Get Loan Info
                            $stmt_loan_info->execute([$extracted_loan_id]);
                            $loan_info = $stmt_loan_info->fetch(PDO::FETCH_ASSOC);
                            if ($loan_info) {
                                $loan_amount = number_format($loan_info['amount'], 2);
                                $loan_status = htmlspecialchars(ucfirst($loan_info['status']));
                            }

                            $display_action = "Released Loan of ₱{$loan_amount} (Status: {$loan_status}) to " . $member_display_name;
                        }
                        // --- END Logic for "Released Loan ID X to Member Y" ---

                        // --- NEW Logic for "Member X, Loan ID Y" ---
                        $pattern_member_loan = '/^Member (\d+), Loan ID (\d+)$/'; // Adjust regex if format varies (e.g., no comma)
                        if (preg_match($pattern_member_loan, $original_action, $matches)) {
                            $extracted_member_id = $matches[1];
                            $extracted_loan_id = $matches[2];

                            $member_display_name = 'Unknown Member';
                            $loan_amount = 'N/A';
                            $loan_status = 'N/A';

                            // Get Member Name
                            $stmt_member_name->execute([$extracted_member_id]);
                            $member_info = $stmt_member_name->fetch(PDO::FETCH_ASSOC);
                            if ($member_info && !empty($member_info['name'])) {
                                $member_display_name = htmlspecialchars($member_info['name']);
                            }

                            // Get Loan Info
                            $stmt_loan_info->execute([$extracted_loan_id]);
                            $loan_info = $stmt_loan_info->fetch(PDO::FETCH_ASSOC);
                            if ($loan_info) {
                                $loan_amount = number_format($loan_info['amount'], 2);
                                $loan_status = htmlspecialchars(ucfirst($loan_info['status']));
                            }

                            // You can customize this message for this specific log type
                            $display_action = "Accessed details for Member " . $member_display_name . " and Loan of ₱{$loan_amount} (Status: {$loan_status})";
                            // Or simpler: $display_action = "Viewed details for " . $member_display_name . "'s loan.";
                        }
                        // --- END NEW Logic for "Member X, Loan ID Y" ---


                        echo "<tr>
                                <td>" . htmlspecialchars($log['created_at']) . "</td>
                                <td>" . $display_action . "</td>
                                <td>" . htmlspecialchars($log['user_full_name'] ?? 'System') . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No activity logs found.</td></tr>";
                }
                ?>
            </table>
        </div>
    </main>

    <script>
    function toggleNotif(event) {
        event.stopPropagation(); // Prevent the document click listener from immediately closing it

        const notif = document.getElementById("notifDropdown");
        const profile = document.getElementById("profileDropdown");

        // Close profile dropdown if it's open
        if (profile.style.display === "block") {
            profile.style.display = "none";
        }

        // Toggle notification dropdown
        // If currently hidden, show as flex; otherwise, hide
        notif.style.display = (notif.style.display === "none" || notif.style.display === "") ? "flex" : "none";
    }

    function toggleProfileDropdown(event) {
        event.stopPropagation(); // Prevent the document click listener from immediately closing it

        const profile = document.getElementById("profileDropdown");
        const notif = document.getElementById("notifDropdown");

        // Close notification dropdown if it's open
        if (notif.style.display === "flex") { // Check for "flex" as it's the opened state
            notif.style.display = "none";
        }

        // Toggle profile dropdown
        profile.style.display = (profile.style.display === "none" || profile.style.display === "") ? "block" : "none";
    }

    // Click outside to close both dropdowns
    document.addEventListener("click", function(event) {
        const notifContainer = document.querySelector(".notif-bell"); // Use the bell's container
        const profileContainer = document.querySelector(".profile-container"); // Use the profile's container

        // Check if the click is outside BOTH the notification container AND the profile container
        const clickedOutsideNotif = notifContainer && !notifContainer.contains(event.target);
        const clickedOutsideProfile = profileContainer && !profileContainer.contains(event.target);

        // If clicked outside the notification area, close the notification dropdown
        if (clickedOutsideNotif) {
            document.getElementById("notifDropdown").style.display = "none";
        }
        // If clicked outside the profile area, close the profile dropdown
        if (clickedOutsideProfile) {
            document.getElementById("profileDropdown").style.display = "none";
        }
    });
    </script>

</body>
</html>