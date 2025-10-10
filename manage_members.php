<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check role (admin only)
if ($user['role'] !== 'admin') {
    echo "<script>alert('Access denied!'); window.location='index.php';</script>";
    exit;
}

$msg = '';

// --- 1. Handle Member Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'], $_POST['new_status'])) {
    $member_id = $_POST['member_id'];
    $new_status = $_POST['new_status'];
    
    // Validate status
    if (in_array($new_status, ['active', 'inactive', 'removed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $member_id]);
            
            // Log action
            $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)")
                ->execute([$user_id, "Updated member status (ID: $member_id) to $new_status"]);
            
            $msg = "Member (ID: $member_id) status successfully updated to " . ucfirst($new_status) . ".";
        } catch (PDOException $e) {
            $msg = "Error updating status: " . $e->getMessage();
        }
    } else {
        $msg = "Invalid status selected.";
    }
}


// --- 2. Fetch All Members ---
$members = $pdo->query("
    SELECT 
        m.*, 
        u.username,
        u.email
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    ORDER BY m.status ASC, m.name ASC
")->fetchAll();

// Fetch notifications for header (copied from dashboard structure)
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->execute([$user['id']]);
$notifications = $notif_stmt->fetchAll();
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
$count_stmt->execute([$user['id']]);
$unread_count = $count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Members - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        <?php include 'style.css'; // Assuming style.css contains common styles, or paste styles here for single file mandate ?>
        /* Base Styles (Copied from admin_dashboard.php for consistency) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }
        .sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; position: fixed; }
        .logo-box {
    /* Tinanggal ang h2 styles */
    display: flex;
    justify-content: left; /* I-center ang image */
    align-items: center;
    padding: 15px 0;
    margin-bottom: 30px;
    border-radius: 8px;
    }
    .logo-box img {
        height: 60px; /* Fixed height for the logo */
        width: auto;
        border-radius: 6px; 
}       .sidebar a { display: block; color: #e2e8f0; padding: 10px; margin: 8px 0; border-radius: 6px; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover { background: #1e293b; color: #fff; }
        .main { flex: 1; margin-left: 230px; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        header h1 { font-size: 22px; font-weight: 600; color: #1e3a8a; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .profile { background: #2563eb; color: #fff; padding: 8px 14px; border-radius: 6px; font-weight: 500; }
        .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .card h3 { margin-bottom: 15px; font-size: 18px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f1f5f9; }
        .status { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 14px; }
        .status.active { background: #dcfce7; color: #16a34a; }
        .status.inactive { background: #fefce8; color: #ca8a04; }
        .status.removed { background: #fee2e2; color: #dc2626; }
        .action-form { display: flex; gap: 5px; align-items: center; }
        .action-form select, .action-form button { padding: 6px 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; }
        .action-form button { background: #2563eb; color: white; cursor: pointer; border: none; transition: background 0.2s; }
        .action-form button:hover { background: #1d4ed8; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; font-weight: 600; text-align: center; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="admin_dashboard.php">🏠 Home</a>
        <a href="members.php">👥 Manage Members</a>
        <a href="manage_loans.php">💼 Manage Loans</a>
        <a href="record_payment.php">💰 Record Payments</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="create_user.php">➕ Create Staff / Manager</a>
        <a href="index.php?logout=1">🚪 Logout</a>
    </aside>

    <!-- Main -->
    <main class="main">
        <header>
            <h1>👥 Manage Clients</h1>
            <div class="header-right">
                <!-- Placeholder for notification bell inclusion -->
                <div class="profile">👤 <?= ucfirst($user['role']) ?></div>
            </div>
        </header>
        
        <?php if (!empty($msg)): ?>
            <div class="message success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>All Registered Clients (<?= count($members) ?> Total)</h3>
            
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Linked Username</th>
                    <th>Contact</th>
                    <th>CI Score</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php if ($members): ?>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?= $member['id'] ?></td>
                            <td><?= htmlspecialchars($member['name']) ?></td>
                            <td><?= htmlspecialchars($member['username'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($member['phone']) ?></td>
                            <td><?= $member['ci_score'] ?></td>
                            <td>
                                <span class="status <?= $member['status'] ?>"><?= ucfirst($member['status']) ?></span>
                            </td>
                            <td>
                                <form method="post" class="action-form">
                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                    <select name="new_status" required>
                                        <option value="">Update Status</option>
                                        <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="removed" <?= $member['status'] === 'removed' ? 'selected' : '' ?>>Remove</option>
                                    </select>
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No registered members found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</body>
</html>
