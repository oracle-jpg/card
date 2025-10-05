<?php
require_once 'auth.php';
require_role(['staff']);
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff Dashboard</title>
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
      padding: 25px 20px;
      display: flex;
      flex-direction: column;
    }
    .sidebar h2 { font-size: 20px; margin-bottom: 30px; }
    .sidebar a {
      color: #e2e8f0;
      text-decoration: none;
      padding: 10px;
      margin-bottom: 8px;
      border-radius: 6px;
      display: block;
      transition: 0.3s;
    }
    .sidebar a:hover { background: #1e293b; color: #fff; }

    .logout {
      margin-top: auto;
      background: #dc2626;
      color: #fff;
      text-align: center;
      padding: 10px;
      border-radius: 6px;
      text-decoration: none;
      transition: 0.3s;
    }
    .logout:hover { background: #b91c1c; }

    /* Main */
    .main {
      flex: 1;
      padding: 30px 40px;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    header h1 {
      font-size: 24px;
      font-weight: 600;
      color: #1e3a8a;
    }
    .profile {
      background: #e0f2fe;
      color: #1e3a8a;
      padding: 8px 15px;
      border-radius: 8px;
      font-weight: 500;
    }

    /* Dashboard Cards */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }
    .card {
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 3px 8px rgba(0,0,0,0.08);
      transition: transform 0.2s ease;
    }
    .card:hover { transform: translateY(-3px); }
    .card h3 { color: #0f172a; font-size: 18px; margin-bottom: 10px; }
    .card p { color: #475569; font-size: 14px; margin-bottom: 15px; }
    .card button {
      background: #2563eb;
      border: none;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.3s;
    }
    .card button:hover { background: #1d4ed8; }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <h2>Staff Panel</h2>
    <a href="staff_dashboard.php">🏠 Dashboard</a>
    <a href="record_payment.php">💰 Record Payments</a>
    <a href="members.php">👥 Manage Members</a>
    <a href="upload_member_photo.php">📸 Upload Proof</a>
    <a href="notifications.php">🔔 Notifications</a>
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
  </aside>

  <!-- Main -->
  <main class="main">
    <header>
      <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?> 👋</h1>
      <div class="profile">Staff</div>
    </header>

    <div class="cards">
      <div class="card">
        <h3>💰 Record Client Payments</h3>
        <p>Log payments made by clients through cash or GCash.</p>
        <button onclick="location.href='record_payment.php'">Open</button>
      </div>

      <div class="card">
        <h3>👥 Manage Members</h3>
        <p>Add, update, or check client details under your supervision.</p>
        <button onclick="location.href='members.php'">Open</button>
      </div>

      <div class="card">
        <h3>📸 Upload Proofs</h3>
        <p>Submit business or loan progress photos monthly.</p>
        <button onclick="location.href='upload_member_photo.php'">Open</button>
      </div>

      <div class="card">
        <h3>🔔 Notifications</h3>
        <p>View announcements or instructions from your manager.</p>
        <button onclick="location.href='notifications.php'">Open</button>
      </div>
    </div>
  </main>

</body>
</html>
