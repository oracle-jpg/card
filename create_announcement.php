<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
// FIX: Allow both 'admin' and 'manager' to access this page
require_role(['admin', 'manager']); 
require_once 'db.php';

$creator_user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'guest'; 

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $creator_user_id) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_role = $_POST['target_role'];

    if ($title && $message) {
        // Tiyakin na ginagamit ang prepare statement
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, target_role, creator_user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $message, $target_role, $creator_user_id]);
        $msg = "✅ Notification posted successfully to '{$target_role}' target.";
    } else {
        $msg = "⚠ Please fill all fields.";
    }
} else if (!$creator_user_id) {
    $msg = "Fatal error: User ID not found. Please log in again."; 
}

// DYNAMIC HOME LINK: Tiyak na babalik sa tamang dashboard
$home_link = ($user_role === 'admin') ? 'admin_dashboard.php' : 'manager_dashboard.php';

// I-set ang mga menu links batay sa role
$menu_links = [];
if ($user_role === 'admin') {
    $menu_links = [
        ['href' => $home_link, 'text' => '🏠 Home'],
        ['href' => 'manage_members.php', 'text' => '👥 Members'],
        ['href' => 'manage_loans.php', 'text' => '💼 Loans'],
        ['href' => 'generate_reports.php', 'text' => '📊 Reports'],
        ['href' => 'create_announcement.php', 'text' => '📣 Notification', 'active' => true], // Nandoon ang link para sa Admin
        ['href' => 'logout.php', 'text' => '🚪 Logout'],
    ];
} elseif ($user_role === 'manager') {
    $menu_links = [
        ['href' => $home_link, 'text' => '🏠 Home'],
        ['href' => 'staff_performance.php', 'text' => '👥 Staff'],
        ['href' => 'loan_overview.php', 'text' => '💼 Loans'],
        ['href' => 'manager_loan_approval.php', 'text' => '✅ Approvals'],
        // ADDED: Ibalik ang 'create_announcement.php' sa Manager menu
        ['href' => 'create_announcement.php', 'text' => '📣 Notification', 'active' => true], 
        ['href' => 'logout.php', 'text' => '🚪 Logout'], 
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Notification</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Global Styles */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #eef2f6; color: #1e293b; min-height: 100vh; }
        
        /* --- Header Styles --- */
        .header { 
            background: #0f172a; color: #fff; padding: 10px 30px; 
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000;
        }
        .logo-box img { height: 40px; vertical-align: middle; }
        
        /* Navigation (Desktop) */
        .nav-menu { display: flex; }
        .nav-menu a { 
            color: #94a3b8; text-decoration: none; padding: 10px 15px; 
            margin-left: 5px; border-radius: 6px; transition: background 0.2s, color 0.2s; font-weight: 500; 
        }
        .nav-menu a:hover { background: #1e293b; color: #fff; }
        .nav-menu a.active-link { background: #3b82f6; color: #fff; font-weight: 600; }
        
        /* Mobile Menu */
        #menu-check { display: none; }
        .menu-toggle { display: none; cursor: pointer; font-size: 28px; color: #fff; padding: 5px; }

        /* --- Main Content & Form --- */
        .main-content { padding: 40px 20px; max-width: 1000px; margin: 0 auto; }
        .form-container { 
            background: #fff; padding: 40px; border-radius: 12px; 
            max-width: 550px; margin: 0 auto; 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); 
            border-top: 5px solid #3b82f6; 
        }
        h2 { text-align: center; color: #0f172a; margin-bottom: 30px; font-weight: 700; }
        label { display: block; margin-top: 18px; font-weight: 600; color: #1e293b; }
        input, textarea, select { 
            width: 100%; padding: 12px; margin-top: 8px; border: 1px solid #cbd5e1; 
            border-radius: 8px; box-sizing: border-box; font-size: 1rem; transition: border-color 0.2s; 
        }
        input:focus, textarea:focus, select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
        textarea { resize: vertical; min-height: 100px; }

        /* Action Buttons */
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        button[type="submit"], .back-btn { 
            flex-grow: 1; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; 
            font-weight: 600; text-align: center; text-decoration: none; transition: background 0.2s, color 0.2s;
        }

        button[type="submit"] { background: #3b82f6; color: #fff; }
        button[type="submit"]:hover { background: #2563eb; }

        .back-btn {
            background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1;
        }
        .back-btn:hover { background: #cbd5e1; color: #1e293b; }

        /* Message Styles */
        .msg { margin-bottom: 20px; padding: 12px; border-radius: 8px; font-weight: 600; text-align: center; }
        .msg.success { background: #dcfce7; color: #15803d; border: 1px solid #a7f3d0; }
        .msg.error { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

        /* --- Responsive Design (Mobile) --- */
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-menu { display: none; flex-direction: column; position: absolute; top: 60px; left: 0; width: 100%; background: #0f172a; }
            #menu-check:checked ~ .nav-menu { display: flex; }
            
            .nav-menu a { margin: 0; padding: 15px 30px; border-bottom: 1px solid #1e293b; }
            .header { padding: 10px 15px; }
            .main-content { padding: 15px; }
            .form-container { padding: 25px; margin: 15px auto; }
            .button-group { flex-direction: column; gap: 10px; }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        
        <input type="checkbox" id="menu-check">
        <label for="menu-check" class="menu-toggle">&#9776;</label>

        <nav class="nav-menu">
            <?php foreach ($menu_links as $link): ?>
                <a 
                    href="<?= $link['href'] ?>" 
                    class="<?= isset($link['active']) && $link['active'] ? 'active-link' : '' ?>"
                >
                    <?= $link['text'] ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="main-content">
        <div class="form-container">
            <h2>📰 Create New Notification</h2>
            
            <form method="POST">
                <?php 
                if ($msg) {
                    $class = strpos($msg, '✅') !== false ? 'success' : 'error';
                    echo "<p class='msg $class'>$msg</p>"; 
                }
                ?>
                <label for="title">Title</label>
                <input name="title" id="title" required placeholder="e.g., Important System Maintenance">
                
                <label for="message">Message Content</label>
                <textarea name="message" id="message" rows="4" required placeholder="Write your full announcement message here..."></textarea>
                
                <label for="target_role">Target Audience</label>
                <select name="target_role" id="target_role" required>
                    <option value="all">All Users</option>
                    <option value="manager">Managers Only</option>
                    <option value="staff">Staff Only</option>
                    <option value="client">Clients Only</option>
                </select>
                
                <div class="button-group">
                    <a href="admin_dashboard.php" class="back-btn">⬅️ Back to Home</a>
                    <button type="submit">Post Notification</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>