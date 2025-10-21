<?php
// notification_bell.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Siguraduhin na ang 'db.php' ay tama ang path
require_once 'db.php'; 

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) exit;

// Get user role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?"); 
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$role = $user['role'] ?? '';

// Check if user is admin to allow creating announcements
$can_create_announcement = ($role === 'admin'); 

// Fetch notifications
$stmt = $pdo->prepare("
    SELECT id, title, message, is_read, created_at
    FROM notifications
    WHERE (target_role = 'all' 
           OR target_role = ? 
           OR target_user_id = ?)
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$role, $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter the notifications array to count unread ones
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) {
        $unread_count++;
    }
}
?>

<div class="notif-dropdown">
    <button class="notif-btn" onclick="toggleNotif()">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="notif-count"><?= $unread_count ?></span>
        <?php endif; ?>
    </button>

    <div class="notif-list" id="notifList">
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notification): 
                // Inalis ang anumang scrollable tag. Gumamit lang ng <p>
                $display_message = htmlspecialchars(substr($notification['message'], 0, 100));
                if (strlen($notification['message']) > 100) {
                    $display_message .= '...';
                }
                
                $notif_link = "notification_details.php?id=" . $notification['id'];
            ?>
                <a href="<?= $notif_link ?>" class="notif-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                    <strong><?= htmlspecialchars($notification['title']) ?></strong>
                    <p><?= $display_message ?></p>
                    <small>
                        <?= htmlspecialchars(date('M d, Y h:i A', strtotime($notification['created_at']))) ?>
                    </small>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">No new notifications.</p>
        <?php endif; ?>

        <?php if ($can_create_announcement): ?>
            <a href="create_announcement.php" class="create-announcement-link">
                <i class="fas fa-bullhorn"></i> Create Announcement
            </a>
        <?php endif; ?>
    </div>
</div>