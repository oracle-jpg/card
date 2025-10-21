<?php
require 'auth.php';
require 'db.php';
require_role(['staff']);

// Fetch assigned CIs for this staff
$stmt = $pdo->prepare("
  SELECT ci.id AS ci_id, m.name, m.address, ci.remarks, ci.ci_status 
  FROM credit_investigations ci
  JOIN members m ON ci.member_id = m.id
  WHERE ci.assigned_to = ?
");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ci_id = $_POST['ci_id'];
  $remarks = $_POST['remarks'];
  $recommendation = $_POST['recommendation'];
  $extra_note = $_POST['extra_note'] ?? '';
  $imagePath = null;

  // ✅ Handle photo upload
  if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = 'ci_photo_' . $ci_id . '_' . time() . '_' . basename($_FILES['photo']['name']);
    $targetFile = $uploadDir . $fileName;
    move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile);
    $imagePath = $targetFile;
  }

  // Combine remarks + extra notes
  $finalRemarks = $remarks;
  if (!empty($extra_note)) {
    $finalRemarks .= "\n\nAdditional Findings: " . $extra_note;
  }

  // ✅ Update record
  $stmt = $pdo->prepare("
    UPDATE credit_investigations 
    SET remarks=?, recommendation=?, photo_path=?, ci_status='completed', updated_at=NOW()
    WHERE id=?
  ");
  $stmt->execute([$finalRemarks, $recommendation, $imagePath, $ci_id]);

  echo "<script>alert('✅ CI report with recommended remarks submitted successfully!'); location.href='staff_ci_tasks.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My CI Tasks</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f8fafc;
      color: #1e293b;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 1000px;
      margin: 50px auto;
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    h2 {
      text-align: center;
      color: #1e3a8a;
      margin-bottom: 25px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      font-size: 14px;
    }
    th, td {
      border-bottom: 1px solid #e2e8f0;
      padding: 12px 10px;
      text-align: left;
      vertical-align: top;
    }
    th {
      background: #f1f5f9;
      color: #1e293b;
      text-transform: uppercase;
      font-size: 13px;
      letter-spacing: 0.5px;
    }
    tr:hover { background-color: #f9fafb; }
    textarea, input[type="file"] {
      width: 100%;
      padding: 8px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      margin-top: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 13px;
      resize: vertical;
    }
    select, .radio-group {
      width: 100%;
      margin-top: 8px;
    }
    .radio-group {
      display: flex;
      gap: 15px;
      margin-bottom: 10px;
    }
    .radio-group label {
      cursor: pointer;
      font-weight: 500;
    }
    input[type="radio"] {
      accent-color: #2563eb;
      margin-right: 5px;
    }
    button {
      margin-top: 10px;
      padding: 10px 15px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }
    button:hover { background: #1d4ed8; }
    .status {
      font-weight: 600;
      text-transform: capitalize;
    }
    .status.completed { color: #16a34a; }
    .status.pending { color: #dc2626; }
    .back-btn {
      display: inline-block;
      text-decoration: none;
      background: #0f172a;
      color: #fff;
      padding: 10px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 600;
      transition: background 0.2s;
    }
    .back-btn:hover { background: #1e293b; }
  </style>
</head>
<body>
  <div class="container">
    <a href="staff_dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h2>📋 My Assigned Credit Investigations</h2>

    <table>
      <tr>
        <th>Client Name</th>
        <th>Address</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
      <?php if ($tasks): ?>
        <?php foreach ($tasks as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td><?= htmlspecialchars($t['address']) ?></td>
            <td><span class="status <?= $t['ci_status'] ?>"><?= htmlspecialchars($t['ci_status']) ?></span></td>
            <td>
              <?php if ($t['ci_status'] != 'completed'): ?>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirmSubmit();">
                  <input type="hidden" name="ci_id" value="<?= $t['ci_id'] ?>">

                  <label>Findings / Remarks:</label>
                  <select id="remarksPreset" onchange="setRemarks(this, <?= $t['ci_id'] ?>)">
                    <option value="">-- Select Recommended Remark --</option>
                    <option value="The client was found to have a stable business and good credit standing.">✅ Stable business, good standing</option>
                    <option value="The client has an unstable or inconsistent business operation.">⚠️ Unstable business</option>
                    <option value="The client was not located at the given address or has questionable information.">❌ Client not located / invalid info</option>
                  </select>

                  <textarea id="remarks_<?= $t['ci_id'] ?>" name="remarks" placeholder="Your investigation findings..." required></textarea>

                  <label>Recommendation:</label>
                  <div class="radio-group">
                    <label><input type="radio" name="recommendation" value="approve" required> Approve</label>
                    <label><input type="radio" name="recommendation" value="reject" required> Reject</label>
                  </div>

                  <label>📷 Upload Photo (Proof of Visit / Business):</label>
                  <input type="file" name="photo" accept="image/*" required>

                  <button type="submit">Submit Report</button>
                </form>
              <?php else: ?>
                ✅ Completed
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" style="text-align:center;"><em>No assigned credit investigations.</em></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <script>
  function setRemarks(select, id) {
    const textarea = document.getElementById('remarks_' + id);
    if (select.value) {
      textarea.value = select.value + " "; // Adds spacing for staff to add more details
    } else {
      textarea.value = "";
    }
  }
  function confirmSubmit() {
    return confirm("Are you sure you want to submit this CI report?");
  }
  </script>
</body>
</html>
