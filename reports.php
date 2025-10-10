<?php
require_once 'auth.php'; // Includes db.php, current_user(), require_role()
require_role(['manager', 'admin']); // Only Manager and Admin can access this report
$user = current_user();

// Helper function to determine the main dashboard link based on role
function getDashboardLink($role) {
    if ($role === 'admin') return 'admin_dashboard.php';
    if ($role === 'manager') return 'manager_dashboard.php';
    // Fallback if somehow a staff is here, though restricted by require_role
    if ($role === 'staff') return 'staff_dashboard.php'; 
    return 'index.php'; 
}

$dashboard_link = getDashboardLink($user['role']);
$user_full_name = $user['full_name'] ?? 'System User';
$user_role = $user['role'] ?? 'N/A';
$message = '';

// --- DATA FETCHING (Key Performance Indicators) ---
try {
    // Total Loans Issued
    $total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn() ?? 0;
    // Total Payments Records Count
    $total_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;
    // Total Collected Amount (Only verified payments)
    $total_collected = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;

    // Loans with Remaining Balance (Risk Metric)
    $total_active_loans_query = "
        SELECT COUNT(t.loan_id) FROM (
            SELECT 
                l.id AS loan_id
            FROM loans l 
            LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'verified'
            WHERE l.status IN ('approved', 'ongoing')
            GROUP BY l.id, l.amount
            HAVING (l.amount - IFNULL(SUM(p.amount), 0)) > 0
        ) AS t
    ";
    $loans_with_balance = $pdo->query($total_active_loans_query)->fetchColumn() ?? 0;
    $total_active_loans_count = $pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('approved', 'ongoing')")->fetchColumn() ?? 1;
    $balance_percentage = ($total_active_loans_count > 0) 
        ? ($loans_with_balance / $total_active_loans_count) * 100 
        : 0;

    // Detailed loan list
    $query = "
    SELECT m.name AS member_name, 
            l.amount AS loan_amount,
            l.status AS loan_status,
            IFNULL(SUM(p.amount), 0) AS total_paid,
            (l.amount - IFNULL(SUM(p.amount),0)) AS balance
    FROM loans l
    JOIN members m ON l.member_id = m.id
    LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'verified'
    GROUP BY l.id
    ORDER BY l.created_at DESC
    ";
    $details = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $details = [];
    $message = "<div class='message error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles (Simplified for focus) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; }

        /* Sidebar */
        .sidebar {
            width: 250px;
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
        }
        .sidebar a:hover, .sidebar a.active { background: #1e293b; color: #fff; }
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
            font-size: 28px;
            font-weight: 700;
            color: #1e3a8a;
        }
        .profile {
            background: #2563eb;
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-transform: capitalize;
        }
        
        /* Message Box Styling */
        .message {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            border: 1px solid;
        }
        .message.error {
            background-color: #fef2f2;
            color: #b91c1c;
            border-color: #fca5a5;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            border-left: 5px solid #2563eb;
        }
        .kpi-card h3 {
            font-size: 14px;
            color: #475569;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .kpi-card p {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .kpi-card.collected { border-color: #059669; }
        .kpi-card.risk { border-color: #f59e0b; }

        /* Report Actions */
        .report-actions {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
        }
        .report-actions button, .report-actions a {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
            text-decoration: none; /* for anchor tags */
            display: inline-block;
            text-align: center;
        }
        .report-actions .pdf-btn {
            background: #dc2626; /* Red for PDF */
            color: white;
        }
        .report-actions .pdf-btn:hover { background: #b91c1c; }
        .report-actions .view-btn {
            background: #2563eb; /* Blue for View */
            color: white;
        }
        .report-actions .view-btn:hover { background: #1d4ed8; }

        /* Detail Table */
        .detail-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .detail-table th, .detail-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        .detail-table tr:last-child td {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 12px;
            text-transform: capitalize;
        }
        .status-approved, .status-ongoing { background-color: #dbeafe; color: #1e40af; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-paid { background-color: #dcfce7; color: #047857; }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: unset; padding: 15px 20px; }
            .main { padding: 20px; }
            header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .kpi-grid { grid-template-columns: 1fr; }
            .report-actions { flex-direction: column; }
            
            .detail-table, .detail-table thead, .detail-table tbody, .detail-table th, .detail-table td, .detail-table tr { 
                display: block; 
            }
            .detail-table thead { display: none; }
            .detail-table tr {
                margin-bottom: 15px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
            }
            .detail-table td {
                text-align: right;
                position: relative;
                padding-left: 50%;
                border-bottom: 1px dashed #f1f5f9;
            }
            .detail-table td::before {
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
            .detail-table tr:last-child td { border-bottom: none; }
        }
    </style>
</head>
<body>

    <!-- Sidebar: Fixed the Dashboard Link -->
    <aside class="sidebar">
        <h2><?= strtoupper($user_role) ?> Panel</h2>
        <a href="<?= htmlspecialchars($dashboard_link) ?>">🏠 Back to Dashboard</a> 
        <a href="manage_users.php" class="active">👤 Manage Users</a>
        <a href="reports.php" class="active">📊 Reports</a>
        <a href="audit_log.php">📋 Audit Log</a>
        <a href="index.php?logout=1" class="logout">🚪 Logout</a>
    </aside>

    <!-- Main -->
    <main class="main">
        <header>
            <h1>Comprehensive Loan Reports</h1>
            <div class="profile">👤 <?= htmlspecialchars($user_full_name) ?> (<?= ucfirst($user_role) ?>)</div>
        </header>

        <?= $message ?>

        <!-- KPI Grid -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Total Loans Issued</h3>
                <p><?= number_format($total_loans) ?></p>
            </div>
            <div class="kpi-card collected">
                <h3>Total Amount Collected</h3>
                <p>₱<?= number_format($total_collected, 2) ?></p>
            </div>
            <div class="kpi-card">
                <h3>Total Payments Verified</h3>
                <p><?= number_format($total_payments) ?></p>
            </div>
            <div class="kpi-card risk">
                <h3>Loans with Remaining Balance</h3>
                <p><?= number_format($loans_with_balance) ?> (<?= number_format($balance_percentage, 1) ?>%)</p>
            </div>
        </div>

        <!-- Report Export Actions -->
        <div class="report-actions">
            <!-- These links now point to the files we created previously -->
            <a href="export_report_pdf.php" target="_blank" class="view-btn">
                📄 View PDF Report
            </a>
            <a href="download_report_pdf.php" class="pdf-btn">
                ⬇️ Download PDF Report
            </a>
        </div>

        <!-- Detailed Loan Listing -->
        <h2>Loan Portfolio Breakdown</h2>
        <?php if (count($details) > 0): ?>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Loan Amount</th>
                    <th>Total Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $row): ?>
                <tr>
                    <td data-label="Member Name"><?= htmlspecialchars($row['member_name']) ?></td>
                    <td data-label="Loan Amount">₱<?= number_format($row['loan_amount'], 2) ?></td>
                    <td data-label="Total Paid">₱<?= number_format($row['total_paid'], 2) ?></td>
                    <td data-label="Balance">₱<?= number_format($row['balance'], 2) ?></td>
                    <td data-label="Status">
                        <span class="status-badge status-<?= htmlspecialchars($row['loan_status']) ?>">
                            <?= htmlspecialchars(ucfirst($row['loan_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No loan details were found in the database.</p>
        <?php endif; ?>

    </main>

</body>
</html>
