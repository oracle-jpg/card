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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manager Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { display: flex; background: #f8fafc; color: #1e293b; }

    /* Sidebar */
    .sidebar {
      width: 230px;
      background: #0f172a;
      color: #fff;
      min-height: 100vh;
      padding: 20px;
      position: fixed;
    }
    .sidebar h2 {
      font-size: 20px;
      margin-bottom: 30px;
      text-align: center;
    }
    .sidebar a {
      display: block;
      color: #e2e8f0;
      padding: 10px;
      margin: 8px 0;
      border-radius: 6px;
      text-decoration: none;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: #1e293b;
      color: #fff;
    }

    /* Main */
    .main {
      flex: 1;
      margin-left: 230px;
      padding: 30px;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    header h1 {
      font-size: 22px;
      font-weight: 600;
    }
    .profile {
      background: #2563eb;
      color: white;
      padding: 8px 15px;
      border-radius: 6px;
    }

    .card {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .card h3 {
      margin-bottom: 15px;
      font-size: 18px;
      font-weight: 600;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    table th, table td {
      text-align: left;
      padding: 10px;
      border-bottom: 1px solid #e5e7eb;
    }
    table th {
      background: #f1f5f9;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Manager Panel</h2>
    <a href="manager_dashboard.php">🏠 Home</a>
    <a href="staff_performance.php">👥 Staff Performance</a>
    <a href="loan_overview.php">💼 Loans Overview</a>
    <a href="generate_reports.php">📊 Reports</a>
    <a href="notifications.php">🔔 Notifications <span id="notifCount" style="background:#ef4444;color:white;padding:2px 6px;border-radius:10px;font-size:12px;margin-left:6px;">0</span></a>
    <a href="index.php?logout=1">🚪 Logout</a>
  </aside>

  <!-- Main -->
  <main class="main">
    <header>
      <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?> (Manager)</h1>
      <div class="profile">Logged in</div>
    </header>

    <!-- System Overview -->
    <div class="card">
      <h3>📈 Branch Overview</h3>
      <table>
        <tr><th>Category</th><th>Count</th></tr>
        <tr><td>Total Staff</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn(); ?></td></tr>
        <tr><td>Total Clients</td><td><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(); ?></td></tr>
        <tr><td>Total Loans</td><td><?= $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(); ?></td></tr>
        <tr><td>Total Payments</td><td><?= $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(); ?></td></tr>
      </table>
    </div>

    <!-- Staff Performance -->
    <div class="card">
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
          GROUP BY u.id
          ORDER BY total_collections DESC
        ")->fetchAll();

        if ($staffPerf) {
          foreach ($staffPerf as $s) {
            echo "<tr>
                    <td>{$s['full_name']}</td>
                    <td>{$s['total_collections']}</td>
                    <td>{$s['last_activity']}</td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='3'>No staff performance data available.</td></tr>";
        }
        ?>
      </table>
    </div>

    <!-- Recent Activity -->
    <div class="card">
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
                    <td>{$log['created_at']}</td>
                    <td>{$log['action']}</td>
                    <td>{$log['full_name']}</td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='3'>No activity logs found.</td></tr>";
        }
        ?>
      </table>
    </div>
  </main>

</body>
</html>
