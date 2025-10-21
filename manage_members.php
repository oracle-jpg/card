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
$error_msg = '';

// Helper function to get member name by ID
function getMemberNameById($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT name FROM members WHERE id = ?");
    $stmt->execute([$member_id]);
    $result = $stmt->fetchColumn();
    return $result ? $result : "Unknown Member"; // Return name or 'Unknown Member'
}


// --- 1. Handle Member Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id_status'], $_POST['new_status'])) {
    $member_id = $_POST['member_id_status'];
    $new_status = $_POST['new_status'];
    $member_name = getMemberNameById($pdo, $member_id); // Get member name

    // Validate status
    if (in_array($new_status, ['active', 'inactive', 'removed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $member_id]);
            
            // Log action
            $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)")
                ->execute([$user_id, "Updated member status (ID: $member_id, Name: $member_name) to $new_status"]);
            
            // Use member_name in the success message
            $msg = "Member '" . htmlspecialchars($member_name) . "' status successfully updated to " . ucfirst($new_status) . ".";
        } catch (PDOException $e) {
            $error_msg = "Error updating status for '" . htmlspecialchars($member_name) . "': " . $e->getMessage();
        }
    } else {
        $error_msg = "Invalid status selected for Member '" . htmlspecialchars($member_name) . "'.";
    }
}

// --- 2. Handle CI Score Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id_ci'], $_POST['new_ci_score'])) {
    $member_id = $_POST['member_id_ci'];
    $new_ci_score = (int)$_POST['new_ci_score'];
    $member_name = getMemberNameById($pdo, $member_id); // Get member name
    
    // Basic validation for CI score (e.g., non-negative)
    if ($new_ci_score >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE members SET ci_score = ? WHERE id = ?");
            $stmt->execute([$new_ci_score, $member_id]);
            
            // Log action
            $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)")
                ->execute([$user_id, "Updated member CI score (ID: $member_id, Name: $member_name) to $new_ci_score"]);
            
            // Use member_name in the success message
            $msg = "Member '" . htmlspecialchars($member_name) . "' CI Score successfully updated to " . $new_ci_score . ".";
        } catch (PDOException $e) {
            $error_msg = "Error updating CI Score for '" . htmlspecialchars($member_name) . "': " . $e->getMessage();
        }
    } else {
        $error_msg = "Invalid CI Score for Member '" . htmlspecialchars($member_name) . "'. Must be a non-negative number.";
    }
}


// --- 3. Fetch All Members with Search Functionality ---
$search_term = '';
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = '%' . $_GET['search'] . '%'; // Add wildcards for LIKE search
}

$sql = "
    SELECT 
        m.id, m.name, m.ci_score, m.loan_amount, m.loan_start, m.status, m.user_id,
        u.username,
        u.email,
        u.phone AS user_phone,
        u.address AS user_address
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
";

$where_clauses = [];
$params = [];

if ($search_term !== '') {
    $where_clauses[] = "(m.name LIKE ? OR u.username LIKE ? OR u.phone LIKE ? OR u.address LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY m.status ASC, m.name ASC";

$stmt_members = $pdo->prepare($sql);
$stmt_members->execute($params);
$members = $stmt_members->fetchAll();


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
        <?php include 'style.css'; ?> /* Assuming style.css contains common styles, or paste styles here for single file mandate */
        /* Base Styles (Copied from admin_dashboard.php for consistency) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }
        .sidebar { width: 230px; background: #0f172a; color: #fff; min-height: 100vh; padding: 20px; position: fixed; }
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
        .sidebar a { display: block; color: #e2e8f0; padding: 10px; margin: 8px 0; border-radius: 6px; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover { background: #1e293b; color: #fff; }
        .main { flex: 1; margin-left: 230px; padding: 30px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        header h1 { font-size: 22px; font-weight: 600; color: #1e3a8a; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .profile { background: #2563eb; color: #fff; padding: 8px 14px; border-radius: 6px; font-weight: 500; }
        .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .card h3 { margin-bottom: 15px; font-size: 18px; font-weight: 600; }
        
        /* Table and Column Styling */
        table { 
            width: 100%; 
            border-collapse: separate; /* Changed to separate for rounded corners on cells */
            border-spacing: 0; /* Remove space between cells */
            margin-top: 15px; 
            font-size: 14px; /* Smaller font for table content */
            border: 1px solid #e2e8f0; /* Outer border for the table */
            border-radius: 8px; /* Rounded corners for the entire table */
            overflow: hidden; /* Ensures rounded corners are applied */
        }
        table th, table td { 
            text-align: left; 
            padding: 12px 15px; /* Increased horizontal padding */
            border-bottom: 1px solid #e2e8f0; 
        }
        table th { 
            background: #f1f5f9; 
            font-weight: 600; 
            color: #475569;
            text-transform: uppercase;
            font-size: 13px;
        }
        table tbody tr:last-child td {
            border-bottom: none; /* No border on the last row */
        }
        table tbody tr:hover {
            background-color: #f8fafc; /* Subtle hover effect on rows */
        }

        /* Specific Column Widths for better arrangement */
        table th:nth-child(1), /* Name */
        table td:nth-child(1) { width: 18%; }
        table th:nth-child(2), /* Linked Username */
        table td:nth-child(2) { width: 15%; }
        table th:nth-child(3), /* Contact */
        table td:nth-child(3) { width: 15%; }
        table th:nth-child(4), /* Address */
        table td:nth-child(4) { width: 20%; }
        table th:nth-child(5), /* CI Score */
        table td:nth-child(5) { width: 12%; text-align: center; } /* Center CI Score content */
        table th:nth-child(6), /* Status */
        table td:nth-child(6) { width: 10%; text-align: center; } /* Center Status content */
        table th:nth-child(7), /* Action */
        table td:nth-child(7) { width: 10%; }


        .status { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 12px; display: inline-block; }
        .status.active { background: #dcfce7; color: #16a34a; }
        .status.inactive { background: #fefce8; color: #ca8a04; }
        .status.removed { background: #fee2e2; color: #dc2626; }
        
        /* Action Forms Styling */
        .action-form, .ci-form { 
            display: flex; 
            gap: 5px; 
            align-items: center; 
            flex-wrap: wrap; /* Allow wrapping on small screens */
            justify-content: center; /* Center items within the form */
        }
        .action-form select, .action-form button, .ci-input { 
            padding: 6px 10px; 
            border-radius: 6px; 
            border: 1px solid #cbd5e1; /* Lighter border */
            font-size: 13px; 
            background-color: #fff;
        }
        .ci-input { 
            width: 50px; /* Slightly smaller width */
            text-align: center; 
            -moz-appearance: textfield; /* Firefox */
        }
        .ci-input::-webkit-outer-spin-button,
        .ci-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .action-form button, .ci-form button { 
           
            color: #1d4ed8; 
            cursor: pointer; 
            border: none; 
            transition: background 0.2s; 
        }
        .action-form button:hover, .ci-form button:hover { background: #FFDF00; }
        
        /* Message Styling */
        .message { padding: 10px; margin-bottom: 20px; border-radius: 6px; font-weight: 600; text-align: center; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
        .error { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; } 

        /* Search Bar Styling */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-container input[type="search"] {
            flex-grow: 1; /* Allows input to take up most space */
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            max-width: 300px; /* Limit max width */
        }
        .search-container button {
            padding: 10px 15px;
            background-color: #0f172a; /* Darker button for search */
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .search-container button:hover {
            background-color: #1e293b;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; display: flex; flex-wrap: wrap; justify-content: center; padding: 10px; }
            .sidebar .logo-box { margin-bottom: 10px; width: 100%; text-align: center; }
            .sidebar a { width: calc(50% - 16px); text-align: center; }
            .main { margin-left: 0; padding: 15px; }
            header { flex-direction: column; align-items: flex-start; }
            .header-right { margin-top: 10px; }
            table { font-size: 12px; }
            table th, table td { padding: 8px 10px; }
            /* Adjust column widths for better fit on small screens */
            table th:nth-child(1), table td:nth-child(1) { width: 25%; } /* Name */
            table th:nth-child(2), table td:nth-child(2) { width: 20%; } /* Linked Username */
            table th:nth-child(3), table td:nth-child(3) { display: none; } /* Hide Contact */
            table th:nth-child(4), table td:nth-child(4) { width: 25%; } /* Address */
            table th:nth-child(5), table td:nth-child(5) { width: 15%; text-align: center; } /* CI Score */
            table th:nth-child(6), table td:nth-child(6) { width: 15%; text-align: center; } /* Status */
            table th:nth-child(7), table td:nth-child(7) { width: auto; } /* Action */

            .search-container { flex-direction: column; align-items: stretch; }
            .search-container input[type="search"] { max-width: 100%; }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="admin_dashboard.php">🏠 Home</a>
        <a href="manage_members.php">👥 Manage Members</a>
        <a href="manage_loans.php">💼 Manage Loans</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="create_user.php">➕ Create  Personnel</a>
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
        <?php if (!empty($error_msg)): ?>
            <div class="message error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>All Registered Clients (<?= count($members) ?> Total)</h3>
            
            <div class="search-container">
                <form method="get" class="search-form">
                    <input type="search" name="search" placeholder="Search by Name, Username, Phone, or Address" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit">Search</button>
                    <?php if (isset($_GET['search']) && $_GET['search'] !== ''): ?>
                        <a href="manage_members.php" style="text-decoration: none; margin-left: 5px; color: #ef4444;">Clear Search</a>
                    <?php endif; ?>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Linked Username</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th style="text-align: center;">CI Score</th>
                        <th style="text-align: center;">Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= htmlspecialchars($member['name']) ?></td>
                                <td><?= htmlspecialchars($member['username'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($member['user_phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($member['user_address'] ?? 'N/A') ?></td>
                                <td>
                                    <form method="post" class="ci-form">
                                        <input type="hidden" name="member_id_ci" value="<?= $member['id'] ?>">
                                        <input type="number" name="new_ci_score" value="<?= $member['ci_score'] ?>" min="0" class="ci-input">
                                        <button type="submit">Update CI</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="status <?= $member['status'] ?>"><?= ucfirst($member['status']) ?></span>
                                </td>
                                <td>
                                    <form method="post" class="action-form">
                                        <input type="hidden" name="member_id_status" value="<?= $member['id'] ?>">
                                        <select name="new_status" required>
                                            <option value="">Update Status</option>
                                            <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="removed" <?= $member['status'] === 'removed' ? 'selected' : '' ?>>Remove</option>
                                        </select>
                                        <button type="submit">Update Status</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; padding: 20px;">No registered members found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>