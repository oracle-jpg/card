    <?php
    session_start();
    require 'db.php';

    // Redirect if not logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Fetch notifications for current user or broadcast
    $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notif_stmt->execute([$user['id']]);
    $notifications = $notif_stmt->fetchAll();

    // Count unread notifications
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0");
    $count_stmt->execute([$user['id']]);
    $unread_count = $count_stmt->fetchColumn();

    // Get linked member record — auto-create if missing
    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();

    if (!$member) {
        // Auto-create member record if missing
        $stmt = $pdo->prepare("INSERT INTO members (user_id, name, status, created_at) VALUES (?, ?, 'active', NOW())");
        $stmt->execute([$user_id, $user['full_name']]);

        // Re-fetch
        $stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $member = $stmt->fetch();
    }

    $member_id = $member ? $member['id'] : null;

    // =========================================================
    // LOAN SUMMARY
    // =========================================================

    // Utility function to calculate total payable
    function calculateTotalPayable($principal, $rate, $term_months) {
        $term_in_years = $term_months / 12;
        $interest_amount = $principal * ($rate / 100) * $term_in_years;
        return round($principal + $interest_amount, 2);
    }

    $total_loan_amount_history = 0;
    $total_paid_history = 0;
    $outstanding = 0;
    $active_loan_balance = 0;
    $has_active_loan = false;

    // 1. Fetch ALL loans (approved, ongoing, paid, defaulted) to calculate total history
    $all_loans_stmt = $pdo->prepare("
        SELECT * FROM loans
        WHERE member_id = ?
        AND status IN ('approved', 'ongoing', 'paid', 'defaulted')
    ");
    $all_loans_stmt->execute([$member_id]);
    $all_loans = $all_loans_stmt->fetchAll();

    foreach ($all_loans as $loan) {
        $loan_id = $loan['id'];

        // Calculate Total Payable for this specific loan
        $total_payable = calculateTotalPayable(
            $loan['amount'],
            $loan['interest_rate'],
            $loan['term_months']
        );

        // Only Verified Payments are Counted!
        $total_paid_loan_stmt = $pdo->prepare("
            SELECT SUM(amount) AS loan_paid
            FROM payments
            WHERE loan_id = ? AND status = 'verified'
        ");
        $total_paid_loan_stmt->execute([$loan_id]);
        $loan_paid = $total_paid_loan_stmt->fetchColumn() ?? 0;

        // Add to history totals
        $total_paid_history += $loan_paid;

        // Check for Active Loans
        if ($loan['status'] === 'approved' || $loan['status'] === 'ongoing' || $loan['status'] === 'defaulted') {
            $has_active_loan = true;
            $current_outstanding_for_loan = max(0, $total_payable - $loan_paid);

            if ($current_outstanding_for_loan > 0) {
                $active_loan_balance += $current_outstanding_for_loan; // Total outstanding balance across all truly active loans
            }
        }

        $total_loan_amount_history += $loan['amount'];
    }

    $total_loan = $total_loan_amount_history;
    $total_paid = $total_paid_history;
    $outstanding = $active_loan_balance;


    // Recent payments
    $stmt = $pdo->prepare("
        SELECT p.payment_date, p.amount, p.method, p.status
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        WHERE l.member_id = ?
        AND l.status IN ('approved', 'ongoing', 'paid', 'defaulted')
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $recent_payments = $stmt->fetchAll();

    // Latest proof
    $stmt = $pdo->prepare("SELECT * FROM member_photos WHERE member_id = ? ORDER BY submitted_date DESC LIMIT 1");
    $stmt->execute([$member_id]);
    $latest_proof = $stmt->fetch();


    // =========================================================
    // PROOF DUE DATE LOGIC (REVISED - Based on Loan Start Date)
    // =========================================================
    $next_proof_due_date = null;
    $proof_overdue = false;
    // $due_day_of_month is now dynamic, based on loan's start date.

    if ($member_id && $has_active_loan) {
        $current_date = new DateTime();
        $latest_proof_date = null;

        if ($latest_proof && isset($latest_proof['submitted_date'])) {
            $latest_proof_date = new DateTime($latest_proof['submitted_date']);
        }

        // Get the earliest 'approved' or 'ongoing' loan's created_at as the baseline for the *first* proof due date
        $active_loan_info_stmt = $pdo->prepare("
            SELECT created_at FROM loans
            WHERE member_id = ? AND status IN ('approved', 'ongoing')
            ORDER BY created_at ASC LIMIT 1
        ");
        $active_loan_info_stmt->execute([$member_id]);
        $earliest_active_loan = $active_loan_info_stmt->fetch();

        if ($earliest_active_loan) {
            $loan_start_date = new DateTime($earliest_active_loan['created_at']);
            $due_day_of_month_for_loan = (int)$loan_start_date->format('d'); // e.g., if loan started Oct 17, due day is 17
        } else {
            // Fallback if somehow has_active_loan is true but no loan found.
            // This case should ideally not be reached if $has_active_loan is correctly set.
            $due_day_of_month_for_loan = 1; // Default to 1st if no loan start date can be determined
        }

        // --- Calculate the NEXT DUE DATE ---
        $next_due = clone $current_date; // Start from current date for calculations

        // Target the specific day of the month based on loan_start_date
        $target_day = $due_day_of_month_for_loan;

        // Set the day of the month for $next_due
        $next_due->setDate($current_date->format('Y'), $current_date->format('m'), $target_day);

        // Adjust if the current date is past the target day in the current month
        if ($current_date->format('Y-m-d') > $next_due->format('Y-m-d')) {
            $next_due->modify('+1 month');
        }
        // Handle cases where the target_day might be beyond the max days of the month (e.g., Feb 30)
        // DateTime handles this gracefully, but if it ends up on a different day, we might need a specific check.
        // For example, if loan starts on 30th, and next month is Feb, it will become Feb 28/29. This is usually acceptable.

        $next_proof_due_date = $next_due->format('M d, Y'); // This is the date we display for the *next* upload

        // --- Determine OVERDUE STATUS ---
        // Proof is overdue if:
        // 1. No proof ever submitted for this member, AND the current date is past the *first expected due date*.
        //    The first expected due date should be one month after the loan's created_at.
        // 2. Proof(s) submitted, but the latest proof is from a previous *due cycle*,
        //    AND the current date is past *this cycle's due date*.

        // Calculate the expected due date for the *current cycle* (the one we're checking against for overdue)
        $current_cycle_due_date = (clone $current_date);
        $current_cycle_due_date->setDate(
            $current_date->format('Y'),
            $current_date->format('m'),
            $target_day
        );
        // If current date is before the target day this month, the current cycle's actual due was last month.
        // This handles cases like: loan starts Oct 17. It's Nov 10. Next due is Nov 17. Last due was Oct 17.
        // But if it's Nov 20. Next due is Dec 17. Last due was Nov 17.
        if ($current_date->format('d') < $target_day) {
            $current_cycle_due_date->modify('-1 month');
        }


        if (!$latest_proof_date) {
            // Case 1: No proof ever submitted for this member.
            // The *first* proof is due one month after the loan's start date.
            $first_proof_due_date = (clone $loan_start_date)->modify('+1 month');

            if ($current_date > $first_proof_due_date) {
                $proof_overdue = true;
            }
            // If not overdue yet, and no proof is uploaded,
            // we should set $next_proof_due_date to this $first_proof_due_date for display.
            if (!$proof_overdue) {
                $next_proof_due_date = $first_proof_due_date->format('M d, Y');
            }

        } else {
            // Case 2: Proof(s) have been submitted.
            // We need to check if the latest proof covers the period up to the *last* due date.
            // Example: Loan starts Oct 17. Due dates are Nov 17, Dec 17, Jan 17.
            // If it's Nov 20, the Nov 17 due date has passed.
            // If the latest proof date is *before* the current cycle's due date, it's overdue.
            if ($latest_proof_date < $current_cycle_due_date) {
                $proof_overdue = true;
            } else {
                $proof_overdue = false; // Latest proof covers the current or a future period.
            }
        }
    }
    // =========================================================
    // Recent activity (logs table used for client activity)
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Client Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Resets and Typography */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }
    body {
        display: flex;
        background: #f8fafc;
        color: #1e293b;
    }

    /* Sidebar */
    .sidebar {
        width: 230px;
        background: #0f172a;
        color: #fff;
        min-height: 100vh;
        padding: 25px 20px;
        display: flex;
        flex-direction: column;
        position: fixed;
        z-index: 1000; /* Ensure sidebar is above other content if overlapping */
    }
    .logo-box {
        display: flex;
        justify-content: left;
        align-items: center;
        padding: 15px 0;
        margin-bottom: 30px;
        border-radius: 8px;
    }
    .logo-box img {
        height: 60px;
        width: auto;
        border-radius: 6px;
    }
    .sidebar a {
        color: #e2e8f0;
        text-decoration: none;
        padding: 10px;
        margin-bottom: 8px;
        border-radius: 6px;
        display: block;
        transition: 0.3s;
    }
    .sidebar a:hover {
        background: #1e293b;
        color: #fff;
    }
    .logout {
        margin-top: auto;
        background: #dc2626;
        color: #fff;
        text-align: center;
        padding: 10px;
        border-radius: 6px;
        text-decoration: none;
    }
    .logout:hover {
        background: #b91c1c;
    }

    /* Main Content Area */
    .main {
        flex: 1;
        padding: 30px 40px;
        margin-left: 230px; /* Offset for fixed sidebar */
    }

    /* Header Section */
    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    header h1 {
        font-size: 22px;
        font-weight: 600;
        color: #1e3a8a;
    }
    .header-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    /* Profile Dropdown */
    .profile-container {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 6px;
        background: #2563eb;
        color: white;
    }
    .profile-container:hover {
        background: #1d4ed8;
    }
    .profile-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }
    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        width: 250px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        margin-top: 5px;
        overflow: hidden;
        display: none; /* Hidden by default */
    }
    .dropdown-menu.show {
        display: block; /* Show when 'show' class is added by JS */
    }
    .dropdown-menu .user-details {
        padding: 10px 15px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 5px;
        color: #1e293b;
    }
    .dropdown-menu .user-details p {
        font-weight: 600;
        margin: 0;
    }
    .dropdown-menu .user-details small {
        color: #64748b;
        font-size: 12px;
        display: block;
    }
    .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        text-decoration: none;
        color: #1e293b;
        transition: background-color 0.2s;
    }
    .dropdown-menu a:hover {
        background: #f1f5f9;
    }

    /* Notification Bell */
    .notif-container {
        position: relative;
        display: inline-block;
        cursor: pointer;
    }
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
    .notif-bell .count {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ef4444;
        color: white;
        font-size: 12px;
        padding: 2px 5px;
        border-radius: 10px;
    }
    .notif-dropdown { /* Renamed from .dropdown to avoid conflict with .dropdown-menu */
        display: none;
        position: absolute;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        width: 280px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 999;
        max-height: 350px;
        overflow-y: auto;
        margin-top: 5px;
    }
    .notif-dropdown.show {
        display: block; /* Show when 'show' class is added by JS */
    }
    .notif-item {
        display: block;
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        color: #1e293b;
    }
    .notif-item:hover {
        background: #f1f5f9;
    }
    .view-all {
        display: block;
        text-align: center;
        padding: 10px;
        background: #2563eb;
        color: white;
        text-decoration: none;
        border-radius: 0 0 8px 8px;
    }
    .view-all:hover {
        background: #1d4ed8;
    }
    .empty {
        text-align: center;
        color: #64748b;
        padding: 15px;
    }

    /* Summary Cards Grid Layout */
    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* 4 columns for desktop */
        gap: 20px;
        margin-bottom: 25px;
    }

    .card {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
    }
    .card h3 {
        color: #475569;
        font-size: 15px;
        margin-bottom: 10px;
    }
    .card p {
        font-size: 22px;
        font-weight: 700;
        color: #1e3a8a;
    }

    /* Grid Specific Spans for 4x2x1x1 logic */
    /* Card 1: Total Loan Amount */
    /* Card 2: Total Payments Made */
    /* Card 3: Active Outstanding Balance */
    /* These first three cards will naturally take 1 column each in the first row. */

    /* Card 4: Last Proof Upload - should be on the second row, spanning 2 columns */
    .cards > .card:nth-of-type(4) {
        grid-column: span 2;
        
    }

    /* Section 1: Need a Loan? - should be on the second row, spanning 2 columns */
    /* Make sure the PHP output order places this after the first three cards */
    .cards > .section:nth-of-type(1) {
        grid-column: span 2;
    }


    /* Proof Upload Reminder Section - spans full width (4 columns) */
    .proof-upload-reminder {
        grid-column: span 4;
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        border: 2px solid #2563eb; /* Default blue border */
    }
    .proof-upload-reminder.overdue {
        border-color: #ef4444; /* Red border for overdue */
        background: #fef2f2; /* Light red background */
    }
    .proof-upload-reminder h3 {
        margin-bottom: 10px;
        font-size: 20px;
        color: #1e3a8a;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .proof-upload-reminder h3 i {
        color: #2563eb;
    }
    .proof-upload-reminder.overdue h3 i {
        color: #ef4444;
    }
    .proof-upload-reminder p {
        font-size: 15px;
        color: #475569;
        margin-bottom: 15px;
        line-height: 1.5;
    }
    .proof-upload-reminder .due-date-text {
        font-weight: 700;
        font-size: 18px;
        color: #1e3a8a;
    }
    .proof-upload-reminder.overdue .due-date-text {
        color: #dc2626;
    }
    .proof-upload-reminder .btn-upload {
        background: #2563eb;
        color: #fff;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-top: 10px;
        transition: background 0.3s ease;
    }
    .proof-upload-reminder .btn-upload:hover {
        background: #1d4ed8;
    }
    .proof-upload-reminder.overdue .btn-upload {
        background: #dc2626; /* Red upload button when overdue */
    }
    .proof-upload-reminder.overdue .btn-upload:hover {
        background: #b91c1c;
    }

    /* Recent Payments Table Card - spans full width (4 columns) */
    .table-card {
        grid-column: span 4;
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px; /* Add margin-bottom here for spacing below the grid */
    }
    .table-card table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .table-card th, .table-card td {
        padding: 10px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
    }
    .table-card th {
        background: #f1f5f9;
    }
    .status-pending {
        background-color: #fefce8;
        color: #a16207;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
    }
    .status-verified {
        background-color: #dcfce7;
        color: #166534;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
    }

    /* Recent Activity Logs */
    /* This section should be placed outside the .cards grid to function as a separate row */
    .logs {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
    }
    .logs h3 {
        margin-bottom: 10px;
        font-size: 18px;
        color: #1e3a8a;
    }
    .logs ul {
        list-style: none;
    }
    .logs li {
        border-bottom: 1px solid #e5e7eb;
        padding: 8px 0;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
        color: #334155;
    }
    .logs li:last-child {
        border-bottom: none;
    }

    /* General Button Style */
    .btn {
        background: #2563eb;
        color: #fff;
        padding: 8px 14px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s ease;
    }
    .btn:hover {
        background: #1d4ed8;
    }

    /* Sidebar "Upload Proof" link highlight */
    .sidebar a.highlight-upload {
        /* No default highlight unless overdue */
        font-weight: 600; /* Still make it bold when recognized as 'highlight-upload' for consistency */
    }
    .sidebar a.highlight-upload.overdue {
        background: #ef4444; /* Red background for overdue */
        color: #fff;
    }
    .sidebar a.highlight-upload.overdue:hover {
        background: #dc2626;
    }


    /* Responsive adjustments for smaller screens */
    @media (max-width: 1200px) {
        .cards {
            grid-template-columns: repeat(2, 1fr); /* 2 columns on medium screens */
        }
        .cards > .card:nth-of-type(4), /* Last Proof Upload */
        .cards > .section:nth-of-type(1) /* Need a Loan? */ {
            grid-column: span 1; /* Reset span for these on smaller screens */
        }
        .proof-upload-reminder,
        .table-card {
            grid-column: span 2; /* Still span full width (2 columns) */
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            position: static; /* Stack sidebar on top for mobile */
            width: 100%;
            min-height: auto;
        }
        .main {
            margin-left: 0;
            padding: 20px;
        }
        .cards {
            grid-template-columns: 1fr; /* Single column on small screens */
        }
        .cards > *,
        .proof-upload-reminder,
        .table-card {
            grid-column: span 1; /* All items span full width */
        }
        header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .header-right {
            width: 100%;
            justify-content: flex-end; /* Push profile/notif to the right */
        }
        .dropdown-menu, .notif-dropdown {
            left: auto;
            right: 0;
            width: 250px;
        }
    }
    </style>
    </head>
    <body>

    <aside class="sidebar">
        <div class="logo-box">
            <img src="https://www.cardmri.com/rbi/wp-content/uploads/2020/01/CMRBI-1.png" alt="Project Logo">
        </div>
        <a href="client_dashboard.php">🏠 Home</a>
        <a href="my_loans.php">💼 Loans</a>
        <a href="my_payments.php">💰 Payments</a>
        <!-- Highlight Upload Proof ONLY if overdue -->
        <a href="upload_photo.php" class="
            <?php if ($proof_overdue) echo 'highlight-upload overdue'; ?>
        ">📸 Upload Proof</a>
        <a href="my_history.php">📜 History</a>

    </aside>

    <main class="main">
        <header>
        <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</h1>

        <div class="header-right">
            <div class="notif-container">
                <div class="notif-bell" onclick="toggleNotifDropdown()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.228 2.362 5.868A1.875 1.875 0 0118.067 21H5.933a1.875 1.875 0 01-1.428-2.382A8.825 8.825 0 005.25 9.75V9zm6-8.25A1.5 1.5 0 0010.5 3h3a1.5 1.5 0 000-3h-3z" clip-rule="evenodd" />
                    </svg>
                    <?php if ($unread_count > 0): ?>
                        <span class="count"><?= $unread_count ?></span>
                    <?php endif; ?>
                </div>

                <div class="notif-dropdown" id="notifDropdown">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $notif): ?>
                            <a href="view_notification.php?id=<?= $notif['id'] ?>" class="notif-item">
                                <span style="font-weight:<?= ($notif['is_read'] == 0) ? '600' : '400' ?>; color:#1e293b;"><?= htmlspecialchars($notif['title']) ?></span>
                                <p style="margin:0; font-size:13px; color:#475569;"><?= htmlspecialchars($notif['message']) ?></p>
                                <span style="font-size:11px; color:#94a3b8; margin-top:3px; display:block;"><?= htmlspecialchars(date('M d, h:i A', strtotime($notif['created_at']))) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <!-- <a href="notifications.php" class="view-all">View All</a> -->
                    <?php else: ?>
                        <p class="empty">No new notifications.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-container" onclick="toggleProfileDropdown()">
                <div class="profile-info">
                    <span><?= ucfirst($user['role']) ?></span>
                    <i class="fas fa-user-circle fa-lg"></i>
                </div>

                <div id="profileDropdown" class="dropdown-menu">
                    <div class="user-details">
                        <p><?= htmlspecialchars($user['full_name']) ?></p>
                        <small><?= htmlspecialchars($user['email']) ?></small>
                    </div>

                    <a href="change_password_page.php">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <a href="index.php?logout=1" style="color:#dc2626;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        </header>

        <div class="cards">
            <div class="card"><h3>Total Loan Amount (History)</h3><p>₱<?= number_format($total_loan, 2) ?></p></div>
            <div class="card"><h3>Total Payments Made (Verified)</h3><p>₱<?= number_format($total_paid, 2) ?></p></div>
            <div class="card" style="border: 2px solid <?= ($outstanding > 0) ? '#ef4444' : '#10b981' ?>;">
                <h3>Active Outstanding Balance</h3>
                <p style="color: <?= ($outstanding > 0) ? '#dc2626' : '#10b981' ?>;">₱<?= number_format($outstanding, 2) ?></p>
            </div>
            <div class="card">
                <h3>Last Proof Upload</h3>
                <p><?= $latest_proof ? htmlspecialchars(date('M d, Y', strtotime($latest_proof['submitted_date']))) : 'No proof yet' ?></p>
            </div>

            <div class="card section loan-request-card" style="grid-column: span 2;">  <!-- This is the "Need a Loan?" section -->
                <h3>💳 Need a Loan?</h3>

                <?php if ($has_active_loan && $outstanding > 0): ?>
                    <p style="color:#dc2626; font-weight:600;">You currently have an active loan with an outstanding balance. Please settle your remaining balance first.</p>
                    <a href="my_loans.php" class="btn" style="background:#f97316;">View Active Loan</a>
                <?php else: ?>
                    <p style="color:#10b981; font-weight:600;">Your account is clear. You can request a new loan.</p>
                    <a href="request_loan.php" class="btn">Request New Loan</a>
                <?php endif; ?>
            </div>

            <!-- Proof Upload Reminder Section -->
            <?php if ($member_id && $has_active_loan): // <--- CHANGE THIS LINE ?>
            <div class="proof-upload-reminder <?= $proof_overdue ? 'overdue' : '' ?>">
                <h3>
                    <?php if ($proof_overdue): ?>
                        <i class="fas fa-exclamation-triangle"></i> Proof Overdue!
                    <?php else: ?>
                        <i class="fas fa-calendar-alt"></i> Monthly Proof Required
                    <?php endif; ?>
                </h3>
                <?php if ($next_proof_due_date): ?>
                    <p>Your next business progress proof is due by <span class="due-date-text"><?= $next_proof_due_date ?></span>.</p>
                    <?php if ($proof_overdue): ?>
                        <p style="color:#dc2626; font-weight:600;">Please upload your proof as soon as possible to avoid issues with your loan or program status.</p>
                    <?php else: ?>
                        <p>Uploading helps us track your business growth and may affect your loan status.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- This case should now only happen if $has_active_loan is true but no proof ever uploaded -->
                    <p>It looks like you haven't uploaded any business proofs yet for your active loan. Please upload your first proof to start tracking your progress.</p>
                <?php endif; ?>

                <a href="upload_photo.php" class="btn-upload">
                    <i class="fas fa-cloud-upload-alt"></i> Upload My Proof Now
                </a>
                <div style="margin-top: 15px; font-size: 13px; color: #64748b; line-height: 1.4;">
                    <p><strong>What to upload:</strong> A clear photo showing your business activity or inventory. This helps us monitor your progress.</p>
                    <p>For example: A photo of your stall with products, active customers, or your workshop in operation.</p>
                </div>
            </div>
            <?php endif; ?>
            <!-- END Proof Upload Reminder -->
            <div class="section table-card"> <!-- This is the "Recent Payments" section -->
                <h3>Recent Payments (Including Pending Declarations)</h3>
                <table>
                    <tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr>
                    <?php if ($recent_payments): ?>
                        <?php foreach ($recent_payments as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['payment_date'] ?? $p['created_at']) ?></td>
                                <td>₱<?= number_format($p['amount'],2) ?></td>
                                <td><?= htmlspecialchars($p['method']) ?></td>
                                <td>
                                    <span class="<?= ($p['status'] === 'verified') ? 'status-verified' : 'status-pending' ?>">
                                        <?= htmlspecialchars(ucfirst($p['status'] ?? 'pending')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No recent payments.</td></tr>
                    <?php endif; ?>
                </table>
                <br>
                <a href="my_payments.php" class="btn">View All Payments</a>
            </div>
        </div> <!-- End .cards grid -->

        <div class="section logs"> <!-- This is the "Recent Activity" section, outside the grid -->
        <h3>Recent Activity</h3>
        <ul>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log):
                    $original_description = $log['action_description'] ?? $log['action'];
                    // Use preg_replace to remove " for Loan #<number>" or " for Loan #<number>."
                    // This will also handle if it's at the end or followed by a period.
                    $cleaned_description = preg_replace('/ for Loan #[0-9]+(\.|$)/', '.', $original_description);
                    // Ensure there's only one period at the end if one was added by replacement
                    $cleaned_description = rtrim($cleaned_description, '.') . '.';
                    // If no period was ever there, just clean whitespace
                    $cleaned_description = trim($cleaned_description);
                    if (substr($cleaned_description, -1) !== '.') { // If it doesn't end with a period, add one
                        $cleaned_description .= '.';
                    }
                    ?>
                    <li>
                        <span><?= htmlspecialchars($cleaned_description) ?></span>
                        <span><?= htmlspecialchars(date('M d, Y', strtotime($log['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No recent activity.</li>
            <?php endif; ?>
        </ul>
    </div>
    </main>

    <script>
        function toggleProfileDropdown() {
            document.getElementById("profileDropdown").classList.toggle("show");
            // Close notification dropdown if open
            document.getElementById("notifDropdown").classList.remove("show");
        }

        function toggleNotifDropdown() {
            document.getElementById("notifDropdown").classList.toggle("show");
            // Close profile dropdown if open
            document.getElementById("profileDropdown").classList.remove("show");
        }

        // Close the dropdowns if the user clicks outside of them
        window.onclick = function(event) {
            if (!event.target.matches('.profile-container') && !event.target.closest('.profile-container')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
            if (!event.target.matches('.notif-bell') && !event.target.closest('.notif-bell')) {
                var notifDropdowns = document.getElementsByClassName("notif-dropdown");
                for (var i = 0; i < notifDropdowns.length; i++) {
                    var openNotifDropdown = notifDropdowns[i];
                    if (openNotifDropdown.classList.contains('show')) {
                        openNotifDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>

    </body>
    </html>