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

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}


// Fetch notifications for current user or broadcast
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_stmt->execute([$user['id']]);
$notifications = $notif_stmt->fetchAll();

// Count unread notifications
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
$count_stmt->execute([$user['id']]);
$unread_count = $count_stmt->fetchColumn();

// Get linked member record — auto-create if missing
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();

if (!$member) {
    // Auto-create member record if missing
    $stmt = $pdo->prepare("INSERT INTO members (user_id, name, status, created_at) VALUES (?, ?, 'active', NOW())");
    $stmt->execute([$user_id, $user['full_name']]);

    // Re-fetch
    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
}

$member_id = $member ? $member['id'] : null;

// =========================================================
// LOAN SUMMARY
// =========================================================

// Utility function to calculate total payable 
function calculateTotalPayable($principal, $rate, $term_months) {
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return round($principal + $interest_amount, 2);
}

$total_loan_amount_history = 0;
$total_paid_history = 0;
$outstanding = 0;
$active_loan_balance = 0;
$has_active_loan = false;

// 1. Fetch ALL loans (approved, ongoing, paid, defaulted) to calculate total history
$all_loans_stmt = $pdo->prepare("
    SELECT * FROM loans 
    WHERE member_id = ? 
    AND status IN ('approved', 'ongoing', 'paid', 'defaulted') 
");
$all_loans_stmt->execute([$member_id]);
$all_loans = $all_loans_stmt->fetchAll();

foreach ($all_loans as $loan) {
    $loan_id = $loan['id'];
    
    // Calculate Total Payable for this specific loan
    $total_payable = calculateTotalPayable(
        $loan['amount'], 
        $loan['interest_rate'], 
        $loan['term_months']
    );
    
    // Only Verified Payments are Counted!
    $total_paid_loan_stmt = $pdo->prepare("
        SELECT SUM(amount) AS loan_paid 
        FROM payments 
        WHERE loan_id = ? AND status = 'verified' 
    ");
    $total_paid_loan_stmt->execute([$loan_id]);
    $loan_paid = $total_paid_loan_stmt->fetchColumn() ?? 0;
    
    // Add to history totals
    $total_paid_history += $loan_paid; 
    
    // Check for Active Loans
    if ($loan['status'] === 'approved' || $loan['status'] === 'ongoing' || $loan['status'] === 'defaulted') {
        $has_active_loan = true;
        $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);
        
        if ($current_outstanding_for_loan > 0) {
             $active_loan_balance += $current_outstanding_for_loan; // Total outstanding balance across all truly active loans
        }
    }
    
    $total_loan_amount_history += $loan['amount'];
}

$total_loan = $total_loan_amount_history; 
$total_paid = $total_paid_history; 
$outstanding = $active_loan_balance;


// Recent payments
$stmt = $pdo->prepare("
    SELECT p.payment_date, p.amount, p.method, p.status 
    FROM payments p
    JOIN loans l ON p.loan_id = l.id
    WHERE l.member_id = ?
    AND l.status IN ('approved', 'ongoing', 'paid', 'defaulted')
    ORDER BY p.payment_date DESC
    LIMIT 5
");
$stmt->execute([$member_id]);
$recent_payments = $stmt->fetchAll();

// Latest proof
$stmt = $pdo->prepare("SELECT * FROM member_photos WHERE member_id = ? ORDER BY submitted_date DESC LIMIT 1");
$stmt->execute([$member_id]);
$latest_proof = $stmt->fetch();

// Recent activity (logs table used for client activity)
$stmt = $pdo->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"); 
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Client Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="style.css"> 
<style>
/* Pansamantalang CSS DITO: 
    Ang lahat ng CSS na nandito ay dapat ilagay sa style.css mo 
    para maging malinis ang client_dashboard.php.
*/
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
body { display:flex; background:#f8fafc; color:#1e293b; }

/* Sidebar */
.sidebar {
    width:230px; background:#0f172a; color:#fff; min-height:100vh;
    padding:25px 20px; display:flex; flex-direction:column;
    position: fixed; 
}
.logo-box {
    display: flex; justify-content: left; align-items: center;
    padding: 15px 0; margin-bottom: 30px; border-radius: 8px;
}
.logo-box img {
    height: 60px; width: auto; border-radius: 6px; 
    }
.sidebar a {
    color:#e2e8f0; text-decoration:none; padding:10px;
    margin-bottom:8px; border-radius:6px; display:block; transition:0.3s;
}
.sidebar a:hover { background:#1e293b; color:#fff; }
.logout {
    margin-top:auto; background:#dc2626; color:#fff;
    text-align:center; padding:10px; border-radius:6px;
    text-decoration:none;
}
.logout:hover { background:#b91c1c; }

/* Main */
.main { flex:1; padding:30px 40px; margin-left: 230px; } 

/* HEADER AREA */
header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 25px;
}
header h1 {
    font-size: 22px; font-weight: 600; color: #1e3a8a;
}
.header-right {
    display: flex; align-items: center; gap: 20px;
}

/* --- UPDATED PROFILE DROPDOWN STYLES --- */
.profile-container {
    position: relative; cursor: pointer; display: flex;
    align-items: center; padding: 5px 10px; border-radius: 6px;
    background: #2563eb; color: white;
}
.profile-container:hover { background: #1d4ed8; }

.profile-info {
    display: flex; align-items: center; gap: 8px; font-weight: 500;
}

.dropdown-menu {
    /* Mag-overlay sa lahat ng bagay (similar to Admin) */
    position: absolute; top: 100%; right: 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    width: 250px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 1001; margin-top: 5px; overflow: hidden;
    display: none; /* Default hidden */
}

/* Header (Name and Email) inside Dropdown */
.dropdown-menu .user-details {
    padding: 10px 15px; 
    border-bottom: 1px solid #e2e8f0; 
    margin-bottom: 5px;
    color: #1e293b;
}
.dropdown-menu .user-details p {
    font-weight: 600; 
    margin: 0;
}
.dropdown-menu .user-details small {
    color: #64748b; 
    font-size: 12px;
    display: block;
}


.dropdown-menu a {
    display: flex; align-items: center; gap: 10px; padding: 10px 15px;
    text-decoration: none; color: #1e293b; transition: background-color 0.2s;
}

.dropdown-menu a:hover { background: #f1f5f9; }
/* --- END UPDATED PROFILE DROPDOWN STYLES --- */


/* Notification Bell Styling (Kept for completeness) */
.notif-container { position: relative; display: inline-block; cursor: pointer; }
.notif-bell {
    position: relative; cursor: pointer; display: flex;
    align-items: center; justify-content: center;
}
.notif-bell svg {
    width: 24px; height: 24px; color: #1e3a8a; transition: 0.3s;
}
.notif-bell svg:hover { color: #2563eb; }
.notif-bell .count {
    position: absolute; top: -6px; right: -6px;
    background: #ef4444; color: white; font-size: 12px;
    padding: 2px 5px; border-radius: 10px;
}
.dropdown {
    display: none; position: absolute; right: 0;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
    width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 999; max-height: 350px; overflow-y: auto;
}
.notif-item { 
    display: block; padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    text-decoration: none; color: #1e293b;
}
.notif-item:hover { background: #f1f5f9; }
.view-all {
    display: block; text-align: center; padding: 10px;
    background: #2563eb; color: white; text-decoration: none;
    border-radius: 0 0 8px 8px;
}
.view-all:hover { background: #1d4ed8; }
.empty { text-align: center; color: #64748b; padding: 15px; }


/* Summary cards */
.cards {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px; margin-bottom:25px;
}
.card {
    background:#fff; padding:20px; border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,0.08);
}
.card h3 { color:#475569; font-size:15px; margin-bottom:10px; }
.card p { font-size:22px; font-weight:700; color:#1e3a8a; }

/* Table */
.table-card table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; }
th { background:#f1f5f9; }
.status-pending { background-color: #fefce8; color: #a16207; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
.status-verified { background-color: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-weight: 600; }

/* Proof and logs */
.section {
    background:#fff; padding:20px; border-radius:10px;
    box-shadow:0 3px 8px rgba(0,0,0,0.08); margin-bottom:25px;
}
.section h3 { margin-bottom:10px; font-size:18px; color:#1e3a8a; }
.logs ul { list-style:none; }
.logs li {
    border-bottom:1px solid #e5e7eb; padding:8px 0;
    font-size:14px; display:flex; justify-content:space-between;
    color:#334155;
}
.btn {
    background:#2563eb; color:#fff; padding:8px 14px;
    border:none; border-radius:6px; cursor:pointer;
    text-decoration:none;
}
.btn:hover { background:#1d4ed8; }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-box">
        <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php">💼 Loans</a>
    <a href="my_payments.php">💰 Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php">📜 History</a>
</aside>

<main class="main">
    <header>
    <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</h1>
    
    <div class="header-right">
        <div class="notif-container">
            <div class="notif-bell" onclick="toggleDropdown()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.228 2.362 5.868A1.875 1.875 0 0118.067 21H5.933a1.875 1.875 0 01-1.428-2.382A8.825 8.825 0 005.25 9.75V9zm6-8.25A1.5 1.5 0 0010.5 3h3a1.5 1.5 0 000-3h-3z" clip-rule="evenodd" />
                </svg>
                <?php if ($unread_count > 0): ?>
                    <span class="count"><?= $unread_count ?></span>
                <?php endif; ?>
            </div>
            
            <div class="dropdown" id="notifDropdown">
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <a href="view_notification.php?id=<?= $notif['id'] ?>" class="notif-item"> 
                            <span style="font-weight:<?= ($notif['is_read'] == 0) ? '600' : '400' ?>; color:#1e293b;"><?= htmlspecialchars($notif['title']) ?></span>
                            <p style="margin:0; font-size:13px; color:#475569;"><?= htmlspecialchars($notif['message']) ?></p>
                            <span style="font-size:11px; color:#94a3b8; margin-top:3px; display:block;"><?= htmlspecialchars(date('M d, h:i A', strtotime($notif['created_at']))) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a href="my_notifications.php" class="view-all">View All</a>
                <?php else: ?>
                    <p class="empty">No new notifications.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-container" onclick="toggleProfileDropdown()"> 
            <div class="profile-info">
                <span><?= ucfirst($user['role']) ?></span>
                <i class="fas fa-user-circle fa-lg"></i>
            </div>

            <div id="profileDropdown" class="dropdown-menu">
                <div class="user-details">
                    <p><?= htmlspecialchars($user['full_name']) ?></p>
                    <small><?= htmlspecialchars($user['email']) ?></small>
                </div>
                
                <a href="change_password_page.php"> 
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="index.php?logout=1" style="color:#dc2626;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    </header>

    <div class="cards">
        <div class="card"><h3>Total Loan Amount (History)</h3><p>₱<?= number_format($total_loan, 2) ?></p></div>
        <div class="card"><h3>Total Payments Made (Verified)</h3><p>₱<?= number_format($total_paid, 2) ?></p></div>
        <div class="card" style="border: 2px solid <?= ($outstanding > 0) ? '#ef4444' : '#10b981' ?>;">
            <h3>Active Outstanding Balance</h3>
            <p style="color: <?= ($outstanding > 0) ? '#dc2626' : '#10b981' ?>;">₱<?= number_format($outstanding, 2) ?></p>
        </div>
        <div class="section">
            <h3>💳 Need a Loan?</h3>
            
            <?php if ($has_active_loan && $outstanding > 0): ?>
                <p style="color:#dc2626; font-weight:600;">You currently have an active loan with an outstanding balance. Please settle your remaining balance first.</p>
                <a href="my_loans.php" class="btn" style="background:#f97316;">View Active Loan</a>
            <?php else: ?>
                <p style="color:#10b981; font-weight:600;">Your account is clear. You can request a new loan.</p>
                <a href="request_loan.php" class="btn">Request New Loan</a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Last Proof Upload</h3>
            <p><?= $latest_proof ? htmlspecialchars(date('M d, Y', strtotime($latest_proof['submitted_date']))) : 'No proof yet' ?></p>
        </div>
    </div>

    <div class="section table-card">
        <h3>Recent Payments (Including Pending Declarations)</h3>
        <table>
            <tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr>
            <?php if ($recent_payments): ?>
                <?php foreach ($recent_payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['payment_date'] ?? $p['created_at']) ?></td>
                        <td>₱<?= number_format($p['amount'],2) ?></td>
                        <td><?= htmlspecialchars($p['method']) ?></td>
                        <td>
                            <span class="<?= ($p['status'] === 'verified') ? 'status-verified' : 'status-pending' ?>">
                                <?= htmlspecialchars(ucfirst($p['status'] ?? 'pending')) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No recent payments.</td></tr>
            <?php endif; ?>
        </table>
        <br>
        <a href="my_payments.php" class="btn">View All Payments</a>
    </div>

    <div class="section logs">
        <h3>Recent Activity</h3>
        <ul>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                    <li>
                        <span><?= htmlspecialchars($log['action_description'] ?? $log['action']) ?></span> 
                        <span><?= htmlspecialchars(date('M d, Y', strtotime($log['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No recent activity.</li>
            <?php endif; ?>
        </ul>
    </div>
</main>

<script>
// --- NOTIFICATION DROPDOWN LOGIC ---
function toggleDropdown() {
    const notifDd = document.getElementById('notifDropdown');
    const profileDd = document.getElementById('profileDropdown');
    
    if (profileDd) profileDd.style.display = 'none'; 
    
    // Toggle Notification Dropdown
    notifDd.style.display = (notifDd.style.display === 'block') ? 'none' : 'block';
}

// --- PROFILE DROPDOWN LOGIC (CHANGE PASS/LOGOUT) ---
function toggleProfileDropdown() {
    const profileDd = document.getElementById('profileDropdown');
    const notifDd = document.getElementById('notifDropdown');

    if (notifDd) notifDd.style.display = 'none'; 

    if (profileDd) {
        profileDd.style.display = (profileDd.style.display === 'block') ? 'none' : 'block';
    }
}

// --- GLOBAL CLICK LISTENER (FINAL LOGIC) ---
window.onclick = function(e) {
    // 1. Close Notification Dropdown ONLY if the click is outside the container
    const notifContainer = e.target.closest('.notif-container');
    if (!notifContainer) {
        const notifDd = document.getElementById('notifDropdown');
        if (notifDd) notifDd.style.display = 'none';
    }

    // 2. Close Profile Dropdown ONLY if the click is outside the container
    const profileContainer = e.target.closest('.profile-container');
    if (!profileContainer) {
        const profileDd = document.getElementById('profileDropdown');
        if (profileDd) profileDd.style.display = 'none';
    }
}
</script>
</body>
</html>