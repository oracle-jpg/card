<?php
// Tiyakin na ito ang UNANG-UNANG LINE sa file. Walang space o newline sa taas.
session_start();
require 'db.php';
require 'auth.php'; // Assuming auth.php handles session start and current_user()
$user = current_user();

// Tiyakin na client lang ang may access
if ($user['role'] !== 'client') {
    header("Location: index.php?access_denied=1");
    exit;
}

$error = '';
$logs = [];

// =========================================================
// LOG FETCHING LOGIC
// Assume a 'logs' table exists with columns: user_id, action_description, created_at
// =========================================================
try {
    $stmt = $pdo->prepare("
        SELECT action_description, created_at 
        FROM logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: Cannot retrieve activity logs. Please ensure the 'logs' table exists. " . $e->getMessage();
}

// NOTE: Para mag-work ang page na ito, kailangan ng isang logs table 
// (CREATE TABLE logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, action_description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP))
// at ang 'log_action' function ay dapat available para i-record ang client activities.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity History</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { display:flex; background:#f8fafc; color:#1e293b; }
        /* Sidebar Styling (Copied from my_payments.php for consistency) */
        .sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:25px 20px;display:flex;flex-direction:column;}
       .logo-box {
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
        .sidebar a{color:#e2e8f0;text-decoration:none;padding:10px;margin-bottom:8px;border-radius:6px;display:block;transition:0.3s;}
        .sidebar a:hover{background:#1e293b;color:#fff;}
        .logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
        .logout:hover{background:#b91c1c;}
        
        /* Main Content Styling */
        .main{flex:1;padding:30px 40px;}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
        header h1{font-size:24px;font-weight:600;color:#1e3a8a;}
        .profile{background:#e0f2fe;color:#1e3a8a;padding:8px 15px;border-radius:8px;font-weight:500;}
        .card{background:#fff;padding:25px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:25px;}
        
        /* Table Styling */
        table{width:100%;border-collapse:collapse;margin-top:15px;}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size: 14px;}
        th{background:#f1f5f9; color: #475569; font-weight: 600;}
        tr:last-child td { border-bottom: none; }
        .no-data{text-align:center;padding:20px;color:#64748b;}
        .error-msg { padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 6px; border: 1px solid #f87171; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
   <div class="logo-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
    </div>
    <a href="client_dashboard.php">🏠 Home</a>
    <a href="my_loans.php">💼 My Loans</a>
    <a href="my_payments.php">💰 My Payments</a>
    <a href="upload_photo.php">📸 Upload Proof</a>
    <a href="my_history.php" style="background:#1e293b;color:#fff;">📜 My History</a>
   
</aside>

<!-- Main -->
<main class="main">
    <header>
        <h1>📜 My Activity History</h1>
        <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (Client)</div>
    </header>

    <div class="card">
        <h2>History of Actions</h2>
        <p style="color: #64748b; margin-bottom: 20px;">This is a record of all the important actions you have taken on the system.</p>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 200px;">Date & Time</th>
                    <th>Action Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('F d, Y h:i A', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['action_description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="no-data">No recorded activity yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>
</body>
</html>
