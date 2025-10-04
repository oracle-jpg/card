<?php
// upload_photo.php
require_once 'auth.php';
require_role(['admin','manager','staff','client']);

// fetch members for select (clients only see their own if applicable)
$user = current_user();

if ($user['role'] === 'client') {
    $stmt = $pdo->prepare("SELECT m.* FROM members m WHERE m.user_id = ?");
    $stmt->execute([$user['id']]);
    $members = $stmt->fetchAll();
} else {
    $members = $pdo->query("SELECT * FROM members ORDER BY name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $member_id = intval($_POST['member_id']);
    $caption = trim($_POST['caption']);
    $submitted_date = $_POST['submitted_date'] ?? date('Y-m-d');

    $f = $_FILES['photo'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $filename = uniqid('photo_') . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $filename;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("INSERT INTO member_photos (member_id, uploaded_by, filename, caption, submitted_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$member_id, $user['id'] ?? null, $filename, $caption, $submitted_date]);
            $message = "Uploaded.";
        } else {
            $message = "Upload move failed.";
        }
    } else {
        $message = "File upload error.";
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Upload Member Photo</title></head><body>
  <h2>Upload Member Photo (Monthly Proof)</h2>
  <?php if(!empty($message)) echo "<p>$message</p>"; ?>
  <form method="post" enctype="multipart/form-data">
    <label>Member<br>
      <select name="member_id" required>
        <?php foreach($members as $m): ?>
          <option value="<?=htmlspecialchars($m['id'])?>"><?=htmlspecialchars($m['name'])?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <label>Photo<br><input type="file" name="photo" accept="image/*" required></label><br>
    <label>Caption<br><input name="caption"></label><br>
    <label>Submitted date<br><input type="date" name="submitted_date" value="<?=date('Y-m-d')?>"></label><br><br>
    <button>Upload</button>
  </form>
  <p><a href="dashboard.php">Back</a></p>
</body></html>
