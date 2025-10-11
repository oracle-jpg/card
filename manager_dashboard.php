<?php
session_start();
require 'db.php';

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

$user_full_name = htmlspecialchars($user['full_name'] ?? 'Manager User');
$user_username = htmlspecialchars($user['username'] ?? 'N/A');
$user_email = htmlspecialchars($user['email'] ?? 'N/A');
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
    /* --- GENERAL DROPDOWN STYLES (Para sa notif at profile) --- */
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
    /* Ang lahat ng Change Password Modal styles ay inalis */
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
    </aside>

    <main class="main manager-dashboard">
        <header>
            <h1>Welcome, <?= $user_full_name ?> (Manager)</h1>
            
            <div class="header-actions">
                
                <?php 
                    require_once 'notification_bell.php'; 
                ?>
                
                <div class="profile-container" onclick="toggleProfileDropdown()"> 
                    <div class="profile-info">
                        <span>Manager</span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </div>

                    <div id="profileDropdown" class="dropdown-menu">
                        <a href="change_password_page.php"> 
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <a href="index.php?logout=1">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
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
                        SELECT u.full_name, 
                                COUNT(p.id) AS total_collections, 
                                MAX(p.created_at) AS last_activity
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
                                <td>" . htmlspecialchars($log['created_at']) . "</td>
                                <td>" . htmlspecialchars($log['action']) . "</td>
                                <td>" . htmlspecialchars($log['full_name'] ?? 'System') . "</td>
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
    
    // --- DROPDOWN LOGIC ---
    function toggleNotif(){
         const list = document.getElementById('notifList');
         const profileDd = document.getElementById('profileDropdown');
         
         if (profileDd && profileDd.style.display === 'block') {
             profileDd.style.display = 'none';
         }
         
         list.style.display = list.style.display === 'block' ? 'none' : 'block';
    }

    function toggleProfileDropdown() {
        const dd = document.getElementById('profileDropdown');
        const notifList = document.getElementById('notifList');
        
        if (notifList && notifList.style.display === 'block') {
             notifList.style.display = 'none';
        }

        dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
    }

    // Global Click Listener
    window.onclick = function(e) {
        if (!e.target.closest('.profile-container')) {
            const profileDd = document.getElementById('profileDropdown');
            if (profileDd) profileDd.style.display = 'none';
        }

        if (!e.target.closest('.notif-dropdown')) { 
             const notifList = document.getElementById('notifList');
             if (notifList) notifList.style.display = 'none';
        }
        
        // Modal closing logic is now removed.
    }
</script>
</body>
</html>