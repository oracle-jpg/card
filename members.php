<?php
require_once 'auth.php';
require_role(['staff']);
require_once 'db.php';
require_once 'send_email.php';

$user = current_user();
$msg = "";

// ✅ Generate random password
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$!';
    return substr(str_shuffle($chars), 0, $length);
}

// ✅ Add new member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    // Default values for loan processing
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);
    $interest_rate = 5.00; // Using 5.00 as default
    $term_months = 12;  // Using 12 as default

    if ($name && $phone) {
        $username = strtolower(explode(' ', $name)[0]) . rand(100,999);
        $temp_pass = generate_password(10);
        $hash_pass = password_hash($temp_pass, PASSWORD_BCRYPT);

        try {
            // Start transaction
            $pdo->beginTransaction();

            // 1. Create User Account
            $stmtUser = $pdo->prepare("
                INSERT INTO users (username, password_hash, role, full_name, email)
                VALUES (?, ?, 'client', ?, ?)
            ");
            $stmtUser->execute([$username, $hash_pass, $name, $email]);
            $user_id = $pdo->lastInsertId();

            // 2. Add Member Record
            // loan_start is present in members table
            $stmtMem = $pdo->prepare("
                INSERT INTO members (user_id, name, address, phone, loan_amount, loan_start, status, created_by)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'active', ?)
            ");
            $stmtMem->execute([$user_id, $name, $address, $phone, $loan_amount, $user['id']]);
            $member_id = $pdo->lastInsertId();

            // 3. FIX: Create Loan Record for the Initial Amount (Corrected INSERT to match provided schema)
            if ($loan_amount > 0) {
                $stmtLoan = $pdo->prepare("
                    INSERT INTO loans (member_id, amount, term_months, interest_rate, disbursed_date, status)
                    VALUES (?, ?, ?, ?, CURDATE(), 'ongoing')
                ");
                // Note: We use CURDATE() for disbursed_date as the loan is being processed now.
                $stmtLoan->execute([$member_id, $loan_amount, $term_months, $interest_rate]);
            }

            // Commit transaction
            $pdo->commit();

            // send email if provided
            if (!empty($email)) {
                sendAccountEmail($email, $name, $username, $temp_pass);
            }

            // 🔔 send notifications
            notifyRole($pdo, 'admin', 'New Member Added', "$name was added by {$user['full_name']}.");
            notifyRole($pdo, 'manager', 'New Member Added', "$name was added by {$user['full_name']}.");
            sendNotification($pdo, $user['id'], 'Member Added', "You successfully added $name.");

            $msg = "✅ Member added successfully!<br>
                    <b>Username:</b> $username<br>
                    <b>Password:</b> $temp_pass<br>";
        } catch (Exception $e) {
            $pdo->rollBack();
            // Display error for debugging
            $msg = "❌ An error occurred: " . $e->getMessage();
        }
    } else {
        $msg = "⚠ Please fill in required fields.";
    }
}

// fetch all members
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name AS created_by_name 
    FROM members m 
    LEFT JOIN users u ON m.created_by = u.id
    ORDER BY m.created_at DESC
");
$stmt->execute();
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Members - Staff</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* --- Layout and Indentation Adjustments --- */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
body{display:flex;background:#f8fafc;color:#1e293b;}

/* Compact Sidebar */
.sidebar{width:230px;background:#0f172a;color:#fff;min-height:100vh;padding:20px; /* Reduced vertical padding */ display:flex;flex-direction:column;}
.sidebar h2{font-size:20px;margin-bottom:15px; /* Reduced margin */ }
.sidebar a{color:#e2e8f0;text-decoration:none;padding:8px 10px; /* Reduced vertical padding */ margin-bottom:6px; /* Reduced margin */ border-radius:6px;display:block;transition:0.3s;}
.sidebar a:hover{background:#1e293b;color:#fff;}
.logout{margin-top:auto;background:#dc2626;color:#fff;text-align:center;padding:10px;border-radius:6px;text-decoration:none;}
.logout:hover{background:#b91c1c;}

/* Compact Main Content and Header Alignment */
.main{flex:1;padding:20px 30px; /* Reduced overall indentation */}
header{display:flex;justify-content:space-between; /* Pushes H1 left and Profile right */ align-items:center;margin-bottom:20px; /* Reduced margin */}
header h1{font-size:24px;font-weight:600;color:#1e3a8a;}
.profile{background:#e0f2fe;color:#1e3a8a;padding:8px 15px;border-radius:8px;font-weight:500;}

/* Compact Card */
.card{background:#fff;padding:20px; /* Reduced padding */ border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:20px; /* Reduced margin */}
form label{display:block;margin-top:10px; /* Reduced margin */ font-weight:500;}
form input{width:100%;padding:8px; /* Reduced padding */ border:1px solid #cbd5e1;border-radius:6px;margin-top:4px; /* Reduced margin */}
button{background:#2563eb;border:none;color:white;padding:10px 20px;border-radius:6px;cursor:pointer;margin-top:15px;}
button:hover{background:#1d4ed8;}
.msg{margin-top:15px;padding:10px;border-radius:6px;background:#dcfce7;color:#166534;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;}
th{background:#f1f5f9;}
</style>
</head>
<body>
<aside class="sidebar">
    <h2>Staff Panel</h2>
    <a href="staff_dashboard.php">🏠 Home</a>
    <a href="record_payment.php">💰 Record Payment</a>
    <a href="members.php">👥 Manage Members</a>
    <a href="upload_member_photo.php">📸 Upload Proof</a>
    <a href="index.php?logout=1" class="logout">🚪 Logout</a>
</aside>

<main class="main">
    <header>
        <!-- Title on the left -->
        <h1>👥 Manage Members</h1>
        <!-- Profile info on the right -->
        <div class="profile"><?= htmlspecialchars($user['full_name']) ?> (Staff)</div>
    </header>

    <div class="card">
        <h2>Add New Member (Walk-in)</h2>
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" required placeholder="Enter full name">
            <label>Email (optional)</label>
            <input type="email" name="email" placeholder="Enter email address">
            <label>Phone Number</label>
            <input type="text" name="phone" required placeholder="09XXXXXXXXX">
            <label>Address</label>
            <input type="text" name="address" placeholder="Enter address">
            <label>Initial Loan Amount (₱)</label>
            <input type="number" step="0.01" name="loan_amount" placeholder="Enter amount">
            <button name="add_member">Add Member</button>
        </form>
        <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    </div>

    <div class="card">
        <h2>📋 Member List</h2>
        <table>
            <tr><th>ID</th><th>Name</th><th>Phone</th><th>Loan (₱)</th><th>Status</th><th>Created By</th></tr>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= $m['id'] ?></td>
                    <td><?= htmlspecialchars($m['name']) ?></td>
                    <td><?= htmlspecialchars($m['phone']) ?></td>
                    <td><?= number_format($m['loan_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($m['status'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($m['created_by_name'] ?? 'System') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>
</body>
</html>
