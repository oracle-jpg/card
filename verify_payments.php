    <?php
    require_once 'auth.php'; // Includes db.php, current_user(), require_role()
    require_role(['staff']);
    $user = current_user();
    $staff_id = $user['id']; // ID of the currently logged-in staff

    // Safe access for display
    $user_full_name = $user['full_name'] ?? 'Staff User'; 
    $user_role = $user['role'] ?? 'staff';
    $message = '';

    // Check if PDO object is available
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        // This happens if db.php failed to run or did not define $pdo
        exit('FATAL ERROR: Database connection object ($pdo) is not available. Please check db.php.');
    }

    // --- DATA FETCHING (for sidebar badge) ---
    $unverified_payments_count = 0;
    try {
        // Only count payments that are pending verification (Requires 'status' column)
        $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    } catch (PDOException $e) {
        // Silently ignore if status column is still missing
    }

    // --- ACTION HANDLER (Includes Transaction for integrity) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['action'])) {
        $payment_id = (int)$_POST['payment_id'];
        $action = $_POST['action'];

        // Start Transaction
        $pdo->beginTransaction(); 

        try {
            // 1. Get payment details before update
            $stmt_check = $pdo->prepare("
                SELECT 
                    p.amount, 
                    l.id AS loan_id,
                    m.user_id, 
                    u.full_name 
                FROM payments p 
                JOIN loans l ON p.loan_id = l.id           /* Joins payment to loan */
                JOIN members m ON l.member_id = m.id       /* Joins loan to member */
                JOIN users u ON m.user_id = u.id           /* Joins member to user */
                WHERE p.id = ?
            ");
            $stmt_check->execute([$payment_id]);
            $payment_info = $stmt_check->fetch();

            if (!$payment_info) {
                $pdo->rollBack(); // Rollback if data fetch fails
                $message = "<div class='message error'>Payment record not found or not linked to a valid loan/member.</div>";
            } else {
                $client_user_id = $payment_info['user_id'];
                $client_name = $payment_info['full_name'];
                $amount = number_format($payment_info['amount'], 2);

                $new_status = ($action === 'verify') ? 'verified' : 'rejected';
                $log_action = ($action === 'verify') ? "Verified payment #$payment_id (₱$amount) for Loan {$payment_info['loan_id']} from $client_name." : "Rejected payment #$payment_id (₱$amount) for Loan {$payment_info['loan_id']} from $client_name.";
                $notif_title = ($action === 'verify') ? "Payment Verified" : "Payment Rejected";
                $notif_msg = ($action === 'verify') ? "Your payment of ₱$amount has been successfully **VERIFIED** by staff." : "Your submitted payment of ₱$amount was **REJECTED**. Please check your proof or contact staff.";
                
                $message_extra = '';

                // 2. Update the payment status (Requires 'status', 'collected_by', and 'verified_at' columns)
                $stmt_update = $pdo->prepare("UPDATE payments 
                                            SET status = ?, collected_by = ?, verified_at = NOW() 
                                            WHERE id = ?");
                $stmt_update->execute([$new_status, $staff_id, $payment_id]);
                
                // --- NEW LOGIC: Check and Update Loan Status (Only on Verify) ---
                if ($action === 'verify') {
                    $loan_id = $payment_info['loan_id'];

                    // a. Get Loan Principal and Total Verified Payments
                    // We use only 'verified' payments to calculate the balance
                    $stmt_balance = $pdo->prepare("
                        SELECT 
                            l.amount AS loan_amount,
                            IFNULL(SUM(p.amount), 0) AS total_verified_paid
                        FROM loans l
                        LEFT JOIN payments p ON l.id = p.loan_id AND p.status = 'verified'
                        WHERE l.id = ?
                        GROUP BY l.id, l.amount
                    ");
                    $stmt_balance->execute([$loan_id]);
                    $loan_data = $stmt_balance->fetch();

                    if ($loan_data) {
                        $remaining_balance = $loan_data['loan_amount'] - $loan_data['total_verified_paid'];

                        if ($remaining_balance <= 0) {
                            // b. Update Loan Status to 'paid' if balance is settled
                            $stmt_loan_update = $pdo->prepare("UPDATE loans SET status = 'paid' WHERE id = ? AND status != 'paid'");
                            $stmt_loan_update->execute([$loan_id]);

                            // Update success message
                            $message_extra = "**Loan ID $loan_id is now fully PAID!**";

                            // c. Log the loan status change
                            if (function_exists('logAudit')) {
                                logAudit($pdo, $staff_id, "Loan ID: $loan_id status automatically updated to 'paid'.", "Loan ID: $loan_id");
                            }
                        }
                    }
                }
                
                // 3. Log the payment verification/rejection action
                if (function_exists('logAudit')) {
                    logAudit($pdo, $staff_id, $log_action, "Payment ID: $payment_id, Loan ID: {$payment_info['loan_id']}, New Status: $new_status");
                }

                // 4. Notify the client
                if (function_exists('sendNotification')) {
                    sendNotification($pdo, $client_user_id, $notif_title, $notif_msg);
                }
                
                // Final success message construction
                if (!empty($message_extra)) {
                    $message = "<div class='message success'>Payment #$payment_id successfully **$new_status**! $message_extra Client has been notified.</div>";
                } else {
                    $message = "<div class='message success'>Payment #$payment_id successfully **$new_status**! Client has been notified.</div>";
                }

                // 5. Commit transaction
                $pdo->commit();

                // Recalculate badge count after action
                $unverified_payments_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
            }

        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback if any error occurred
            // Display specific database error for debugging
            $message = "<div class='message error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // --- DATA FETCHING (for table display) ---
    try {
        // Fetch all pending payments with member details (Uses Loan -> Member relationship)
        // (Requires 'status' and 'proof_img' columns)
        $stmt = $pdo->query("
            SELECT 
                p.id, 
                p.amount, 
                p.method, 
                p.proof_img,
                p.payment_date,
                m.name AS member_name, 
                u.id AS client_user_id,
                l.id AS loan_id /* Added Loan ID for reference */
            FROM payments p
            JOIN loans l ON p.loan_id = l.id        /* Joins payment to loan */
            JOIN members m ON l.member_id = m.id    /* Joins loan to member */
            JOIN users u ON m.user_id = u.id        /* Joins member to user */
            WHERE p.status = 'pending'
            ORDER BY p.created_at ASC
        ");
        $pending_payments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $pending_payments = [];
        $message .= "<div class='message error'>Error fetching data: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Client Payments</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            /* Base Styles (Copied from staff_dashboard for consistency) */
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
                box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            }
            .sidebar h2 { font-size: 20px; margin-bottom: 30px; font-weight: 700; }
            .sidebar a {
                color: #e2e8f0;
                text-decoration: none;
                padding: 12px 10px;
                margin-bottom: 8px;
                border-radius: 8px;
                display: block;
                transition: 0.3s;
                font-weight: 500;
                position: relative;
            }
            .sidebar a:hover, .sidebar a.active { background: #1e293b; color: #fff; }
            
            /* Sidebar Notification Badge */
            .sidebar-link-badge .badge {
                position: absolute;
                top: 50%;
                right: 10px;
                transform: translateY(-50%);
                background: #ef4444; /* Red for alert */
                color: white;
                padding: 2px 7px;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
            }

            .logout {
                margin-top: auto; 
                background: #dc2626;
                color: #fff;
                text-align: center;
                padding: 12px;
                border-radius: 8px;
                text-decoration: none;
                transition: 0.3s;
                font-weight: 600;
            }
            .logout:hover { background: #b91c1c; }

            /* Main Content */
            .main {
                flex: 1;
                padding: 30px 40px;
            }
            header {
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 15px;
                margin-bottom: 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            header h1 {
                font-size: 24px;
                font-weight: 700;
                color: #1e3a8a;
            }
            
            /* Profile Badge */
            .profile {
                background: #2563eb;
                color: #fff;
                padding: 8px 14px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 14px;
            }

            /* Message Box Styling */
            .message {
                padding: 15px 20px;
                margin-bottom: 20px;
                border-radius: 8px;
                font-weight: 500;
                border: 1px solid;
            }
            .message.success {
                background-color: #ecfdf5;
                color: #047857;
                border-color: #34d399;
            }
            .message.error {
                background-color: #fef2f2;
                color: #b91c1c;
                border-color: #fca5a5;
            }
            
            /* Table Styling */
            .payment-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                background: #fff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .payment-table th, .payment-table td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #f1f5f9;
            }
            .payment-table th {
                background: #f1f5f9;
                color: #475569;
                font-weight: 600;
                font-size: 14px;
                text-transform: uppercase;
            }
            .payment-table tr:last-child td {
                border-bottom: none;
            }

            /* Proof Image Link */
            .proof-link {
                color: #2563eb;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s;
            }
            .proof-link:hover {
                color: #1d4ed8;
                text-decoration: underline;
            }

            /* Action Buttons */
            .action-btns button {
                padding: 8px 12px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                margin-right: 8px;
                transition: background 0.2s;
                font-size: 14px;
            }
            .action-btns .verify-btn {
                background: #10b981; /* Emerald */
                color: white;
            }
            .action-btns .verify-btn:hover {
                background: #059669;
            }
            .action-btns .reject-btn {
                background: #f43f5e; /* Rose */
                color: white;
            }
            .action-btns .reject-btn:hover {
                background: #e11d48;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                body { flex-direction: column; }
                .sidebar { width: 100%; min-height: unset; padding: 15px 20px; }
                .main { padding: 20px; }
                header { flex-direction: column; align-items: flex-start; gap: 10px; }
                .payment-table, .payment-table thead, .payment-table tbody, .payment-table th, .payment-table td, .payment-table tr { 
                    display: block; 
                }
                .payment-table thead { display: none; }
                .payment-table tr {
                    margin-bottom: 15px;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                }
                .payment-table td {
                    text-align: right;
                    position: relative;
                    padding-left: 50%;
                    border-bottom: 1px dashed #f1f5f9;
                }
                .payment-table td::before {
                    content: attr(data-label);
                    position: absolute;
                    left: 10px;
                    width: calc(50% - 20px);
                    padding-right: 10px;
                    white-space: nowrap;
                    font-weight: 600;
                    color: #475569;
                    text-align: left;
                }
                .payment-table tr:last-child td { border-bottom: 1px solid #f1f5f9; }
                .payment-table tr:last-child .action-btns td { border-bottom: none; }
            }
        </style>
    </head>
    <body>

        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>Staff Panel</h2>
            <a href="staff_dashboard.php">🏠 Home</a>
            <a href="verify_payments.php" class="active sidebar-link-badge">
                💰 Verify Payments
                <?php if ($unverified_payments_count > 0): ?>
                    <span class="badge"><?= $unverified_payments_count ?></span>
                <?php endif; ?>
            </a>
            <a href="record_payment.php">📝 Record Payments</a>
            <a href="members.php">👥 Manage Members</a>
            <a href="upload_member_photo.php">📸 View Proofs</a>
            <a href="index.php?logout=1" class="logout">🚪 Logout</a>
        </aside>

        <!-- Main -->
        <main class="main">
            <header>
                <h1>Review Pending Payments</h1>
                <div class="profile">👤 <?= htmlspecialchars($user_full_name) ?></div>
            </header>

            <?= $message ?>

            <?php if (count($pending_payments) > 0): ?>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loan ID</th>
                        <th>Member Name</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date Paid</th>
                        <th>Proof Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_payments as $payment): ?>
                    <tr>
                        <td data-label="ID"><?= htmlspecialchars($payment['id']) ?></td>
                        <td data-label="Loan ID">**<?= htmlspecialchars($payment['loan_id']) ?>**</td>
                        <td data-label="Member Name"><?= htmlspecialchars($payment['member_name']) ?></td>
                        <td data-label="Amount">₱<?= number_format($payment['amount'], 2) ?></td>
                        <td data-label="Method"><?= htmlspecialchars(ucfirst($payment['method'])) ?></td>
                        <td data-label="Date Paid"><?= htmlspecialchars($payment['payment_date']) ?></td>
                        <td data-label="Proof Image">
                            <?php if (!empty($payment['proof_img'])): ?>
                                <!-- Link to view the image (view_image.php is next) -->
                                <a href="view_image.php?type=payment&id=<?= $payment['id'] ?>" target="_blank" class="proof-link">
                                    View Proof
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions" class="action-btns">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <button type="submit" name="action" value="verify" class="verify-btn">✅ Verify</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <button type="submit" name="action" value="reject" class="reject-btn">❌ Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="message success">
                🎉 There are no pending payments for review at this time. Check back later.
            </div>
            <?php endif; ?>

        </main>

    </body>
    </html>
