<?php
require_once 'auth.php';
// Tinitiyak na ang user ay staff o may access
require_role(['staff']);
$user = current_user();

// Safe way to get the full_name, using null coalescing operator (??)
// Tinitiyak na hindi mag-e-error kahit hindi ma-fetch ang user object
$user_full_name = $user['full_name'] ?? 'Staff User'; 
$user_role = $user['role'] ?? 'staff';

require_once 'db.php'; // Ensure db.php is loaded for PDO object

// Fetch unverified payments count for notification badge
try {
    // Only count payments that are pending verification
    $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    // If the payments table is missing columns, set count to 0 and log error silently
    $unverified_payments_count = 0;
    // Log $e->getMessage() for backend debugging if needed
}

// Check for required notification bell dependency: notification_bell.php
$notification_bell_html = '<!-- Notification Bell Placeholder: Missing notification_bell.php -->';
if (file_exists('notification_bell.php')) {
    ob_start(); // Start output buffering
    include 'notification_bell.php'; // Include the content
    $notification_bell_html = ob_get_clean(); // Capture the output
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
}
    .sidebar h2 { font-size: 20px; margin-bottom: 30px; font-weight: 700; }
    .sidebar a {
      color: #e2e8f0;
      text-decoration: none;
      padding: 12px 10px;
      margin-bottom: 8px;
      border-radius: 8px;
      display: block;
      transition: 0.3s;
      font-weight: 500;
      position: relative; /* For badge */
    }
    .sidebar a:hover, .sidebar a.active { background: #1e293b; color: #fff; }

    /* Sidebar Notification Badge */
    .sidebar-link-badge .badge {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        background: #ef4444; /* Red for alert */
        color: white;
        padding: 2px 7px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .logout {
      margin-top: auto;
      background: #dc2626;
      color: #fff;
      text-align: center;
      padding: 12px;
      border-radius: 8px;
      text-decoration: none;
      transition: 0.3s;
      font-weight: 600;
    }
    .logout:hover { background: #b91c1c; }

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

    /* Align bell and profile side-by-side */
    .header-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    /* Notification Bell Styling (assuming notification_bell.php content handles this) */
    .notif-bell {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notif-bell svg {
        width: 24px;
        height: 24px;
        color: #1e3a8a;
        transition: 0.3s;
    }

    .notif-bell svg:hover {
        color: #2563eb;
    }

    /* Notification Count Bubble */
    .notif-bell .count {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ef4444;
        color: white;
        font-size: 12px;
        padding: 2px 5px;
        border-radius: 10px;
        line-height: 1;
    }

    /* Profile Badge */
    .profile {
      background: #2563eb;
      color: #fff;
      padding: 8px 14px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
    }


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

    /* Responsive adjustments */
    @media (max-width: 768px) {
        body { flex-direction: column; }
        .sidebar { width: 100%; min-height: unset; padding: 15px 20px; }
        .main { padding: 20px; }
        header { flex-direction: column; align-items: flex-start; gap: 10px; }
        .header-right { align-self: flex-end; }
        .cards { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="logo-box">
        <!-- PALITAN ANG NASA IBABA NG ACTUAL IMAGE URL NG INYONG LOGO. -->
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
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
  </aside>

  <!-- Main -->
  <main class="main">
   <header>
    <!-- Line 229 Fix: Use the sanitized $user_full_name variable -->
    <h1>Welcome, <?= htmlspecialchars($user_full_name) ?> (<?= ucfirst($user_role) ?>)</h1>
    
    <div class="header-right">
      <?= $notification_bell_html ?>
      <div class="profile">👤 <?= ucfirst($user_role) ?></div>
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
  </main>

</body>
</html>
