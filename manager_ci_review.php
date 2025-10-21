<?php
require 'auth.php';
require 'db.php';
require_role(['manager']);

// Fetch all completed CIs awaiting manager review
$stmt = $pdo->query("
  SELECT ci.id AS ci_id, m.name, m.address, ci.remarks, ci.recommendation, ci.ci_status, ci.photo_path
  FROM credit_investigations ci
  JOIN members m ON ci.member_id = m.id
  WHERE ci.ci_status = 'completed' AND ci.final_decision = 'none'
");
$records = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ci_id = $_POST['ci_id'];
  $decision = $_POST['decision'];

  $pdo->prepare("
    UPDATE credit_investigations 
    SET final_decision=?, reviewed_by=?, updated_at=NOW()
    WHERE id=?
  ")->execute([$decision, $_SESSION['user_id'], $ci_id]);

  $pdo->prepare("
    UPDATE members 
    SET ci_status=? 
    WHERE id=(SELECT member_id FROM credit_investigations WHERE id=?)
  ")->execute([$decision == 'approved' ? 'approved' : 'rejected', $ci_id]);

  $manager_id = $_SESSION['user_id'];
  $action = strtoupper($decision) . " Credit Investigation";
  $details = "Manager made a final decision ($decision) for CI ID #$ci_id";
  $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)")
      ->execute([$manager_id, $action, $details]);

  echo "<script>alert('Decision saved successfully!'); location.href='manager_ci_review.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manager CI Review</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    * {margin: 0; padding: 0; box-sizing: border-box;}
    body {
      font-family: 'Inter', sans-serif;
      background: #f8fafc;
      color: #1e293b;
      display: flex;
    }

    .sidebar {
      width: 230px;
      background: #0f172a;
      color: #fff;
      min-height: 100vh;
      padding: 25px 20px;
      display: flex;
      flex-direction: column;
    }
    .logo-box { text-align: center; margin-bottom: 30px; }
    .logo-box img { height: 60px; }
    .sidebar a {
      color: #e2e8f0;
      text-decoration: none;
      padding: 12px 10px;
      margin-bottom: 8px;
      border-radius: 8px;
      display: block;
      transition: 0.3s;
      font-weight: 500;
    }
    .sidebar a:hover, .sidebar a.active {
      background: #1e293b;
      color: #fff;
    }

    .main { flex: 1; padding: 40px; }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    header h1 {
      font-size: 24px;
      font-weight: 700;
      color: #1e3a8a;
    }
    .back-btn {
      background: #2563eb;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .back-btn:hover { background: #1d4ed8; }

    .container {
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 15px;
    }
    th, td {
      border-bottom: 1px solid #e2e8f0;
      padding: 12px 15px;
      vertical-align: top;
    }
    th { background: #f1f5f9; font-weight: 600; color: #334155; }
    tr:hover { background-color: #f9fafb; }

    .action-buttons {
      display: flex;
      gap: 10px;
    }
    button {
      border: none;
      padding: 8px 14px;
      border-radius: 8px;
      cursor: pointer;
      color: white;
      font-weight: 600;
      transition: 0.2s;
    }
    button[name="decision"][value="approved"] { background: #16a34a; }
    button[name="decision"][value="approved"]:hover { background: #15803d; }
    button[name="decision"][value="rejected"] { background: #dc2626; }
    button[name="decision"][value="rejected"]:hover { background: #b91c1c; }

    .photo-preview img {
      width: 120px;
      height: auto;
      border-radius: 6px;
      cursor: pointer;
      transition: 0.2s;
    }
    .photo-preview img:hover { transform: scale(1.05); }

    /* Modal for full image */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.8);
    }
    .modal-content {
      margin: 5% auto;
      display: block;
      max-width: 80%;
      border-radius: 10px;
    }
    .close {
      position: absolute;
      top: 20px;
      right: 40px;
      color: #fff;
      font-size: 40px;
      font-weight: bold;
      cursor: pointer;
    }
    .close:hover { color: #ccc; }

    .no-data {
      text-align: center;
      padding: 20px;
      color: #64748b;
      font-style: italic;
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <div class="logo-box">
      <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Logo">
    </div>
    <a href="manager_dashboard.php">🏠 Home</a>
        <a href="staff_performance.php">👥 Staff Performance</a>
        <a href="loan_overview.php">💼 Loans Overview</a>
        <a href="generate_reports.php">📊 Reports</a>
        <a href="manager_loan_approval.php">✅ Loan Approvals</a>
        <a href="manager_ci_review.php">✅ Review CI Reports</a>
  </aside>

  <main class="main">
    <header>
      <h1>🧾 Credit Investigation Review</h1>
      <button class="back-btn" onclick="window.location.href='manager_dashboard.php'"><i class="fas fa-arrow-left"></i> Back to Home</button>
    </header>

    <div class="container">
      <h2>Pending Reviews</h2>
      <?php if ($records): ?>
      <table>
        <tr>
          <th>Client</th>
          <th>Address</th>
          <th>Remarks</th>
          <th>Recommendation</th>
          <th>Photo Proof</th>
          <th>Action</th>
        </tr>
        <?php foreach ($records as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['address'] ?? 'N/A') ?></td>
          <td><?= nl2br(htmlspecialchars($r['remarks'])) ?></td>
          <td>
            <?php if ($r['recommendation'] === 'approve'): ?>
              <span style="color:#16a34a;font-weight:600;">Recommend Approve</span>
            <?php else: ?>
              <span style="color:#dc2626;font-weight:600;">Recommend Reject</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($r['photo_path']) && file_exists($r['photo_path'])): ?>
              <div class="photo-preview">
                <img src="<?= htmlspecialchars($r['photo_path']) ?>" alt="Proof" onclick="openModal(this.src)">
              </div>
            <?php else: ?>
              <em style="color:#94a3b8;">No photo uploaded</em>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="action-buttons">
              <input type="hidden" name="ci_id" value="<?= $r['ci_id'] ?>">
              <button name="decision" value="approved"><i class="fas fa-check"></i> Approve</button>
              <button name="decision" value="rejected"><i class="fas fa-times"></i> Reject</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="no-data">No pending credit investigations for review.</div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Image Modal -->
  <div id="photoModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImg">
  </div>

  <script>
    function openModal(src) {
      document.getElementById('photoModal').style.display = 'block';
      document.getElementById('modalImg').src = src;
    }
    function closeModal() {
      document.getElementById('photoModal').style.display = 'none';
    }
    window.onclick = function(e) {
      const modal = document.getElementById('photoModal');
      if (e.target == modal) modal.style.display = 'none';
    }
  </script>
</body>
</html>
