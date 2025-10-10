<?php
// Tinitiyak na ang session ay tama na handle at ang user ay authenticated at authorized.
require_once 'auth.php'; 
require_role(['staff', 'manager', 'admin']); 
$user = current_user();

// Helper function to determine the main dashboard link based on role
function getDashboardLink($role) {
    if ($role === 'admin') return 'admin_dashboard.php';
    if ($role === 'manager') return 'manager_dashboard.php';
    if ($role === 'staff') return 'staff_dashboard.php'; 
    return 'index.php'; // Fallback
}

$dashboard_link = getDashboardLink($user['role'] ?? 'staff'); // Use determined link
$user_full_name = $user['full_name'] ?? 'System User';
$user_role = $user['role'] ?? 'staff';
$message = '';

// --- DATE FILTERING SETUP: Using Presets and Custom Inputs ---
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');
$default_preset = 'last_30_days';

// Get current filter selections
$range_preset = $_GET['range_preset'] ?? $default_preset;
$start_date_input = $_GET['start_date'] ?? $default_start_date;
$end_date_input = $_GET['end_date'] ?? $default_end_date;

// Calculate dates based on preset
switch ($range_preset) {
    case 'last_7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        break;
    case 'custom':
        // Use the dates provided by the user when 'custom' is selected
        $start_date = $start_date_input;
        $end_date = $end_date_input;
        break;
    case 'last_30_days':
    default:
        $start_date = $default_start_date;
        $end_date = $default_end_date;
        $range_preset = 'last_30_days';
        break;
}

$start_date_safe = date('Y-m-d', strtotime($start_date));
$end_date_safe = date('Y-m-d', strtotime($end_date));

// Adjust end date to include the entire day (up to 23:59:59) for accurate BETWEEN query
$end_date_safe_inclusive = $end_date_safe . ' 23:59:59';

$filter_message = "Showing payments from " . date('M d, Y', strtotime($start_date_safe)) . " to " . date('M d, Y', strtotime($end_date_safe));

// --- DATA FETCHING (Key Performance Indicators - ALL TIME DATA) ---
try {
    // 1. Total Loans Issued (All Time)
    $total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn() ?? 0;
    
    // 2. Total Payments Records Count (All Time Verified)
    $total_payments_all_time = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;
    
    // 3. Total Collected Amount (All Time Verified)
    $total_collected_all_time = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;

    // 4. Loans with Remaining Balance (Risk Metric - All Time Active Loans)
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

    // --- QUERY 5: FILTERED PAYMENTS (Detailed List for Date Range) ---
    $payments_query = "
        SELECT p.*, m.name AS member_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        JOIN members m ON l.member_id = m.id
        WHERE p.status = 'verified'
        AND p.payment_date BETWEEN :start_date AND :end_date_inclusive
        ORDER BY p.payment_date DESC
    ";
    $stmt_payments = $pdo->prepare($payments_query);
    $stmt_payments->execute([
        'start_date' => $start_date_safe,
        'end_date_inclusive' => $end_date_safe_inclusive
    ]);
    $filtered_payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);

    // --- QUERY 6: Total Collected for Filtered Period (New KPI) ---
    $total_collected_filtered = $pdo->prepare("
        SELECT SUM(amount) FROM payments 
        WHERE status = 'verified' AND payment_date BETWEEN :start_date AND :end_date_inclusive
    ");
    $total_collected_filtered->execute([
        'start_date' => $start_date_safe,
        'end_date_inclusive' => $end_date_safe_inclusive
    ]);
    $total_collected_period = $total_collected_filtered->fetchColumn() ?? 0;
    
    
    // --- QUERY 7: Detailed LOAN Status (All Loans, grouped by ID) ---
    $loan_details_query = "
    SELECT m.name AS member_name, 
            l.amount AS loan_amount,
            l.status AS loan_status,
            IFNULL(SUM(p.amount), 0) AS total_paid,
            (l.amount - IFNULL(SUM(p.amount),0)) AS balance
    FROM loans l
    JOIN members m ON l.member_id = m.id
    LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'verified' 
    GROUP BY l.id, m.name, l.amount, l.status
    ORDER BY m.name ASC
    ";
    $details = $pdo->query($loan_details_query)->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $details = [];
    $filtered_payments = [];
    $total_loans = $total_payments_all_time = $total_collected_all_time = $loans_with_balance = $total_collected_period = 0;
    $balance_percentage = 0;
    $message = "<div class='message error' style='color:#dc2626; text-align:center;'>❌ Database Error: Could not fetch report data.</div>";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Comprehensive Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f0f4f8; color: #1e293b; }
        .container {
            max-width: 1200px;
            margin: 50px auto;
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            position: relative; 
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            color: #1e3a8a;
            border-bottom: 2px solid #eff6ff;
            padding-bottom: 10px;
        }
        h3 {
             font-size: 20px; 
             margin-top: 30px; 
             margin-bottom: 15px; 
             color: #1e3a8a;
             border-left: 4px solid #3b82f6; /* Added subtle border */
             padding-left: 10px;
        }

        /* --- STATS BOXES --- */
        .stats {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat {
            flex: 1;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-value { font-size: 24px; font-weight: 700; margin-top: 5px; }
        .stat-label { font-size: 14px; color: #475569; font-weight: 500; }
        .stat:nth-child(1) .stat-value { color: #0d9488; }
        .stat:nth-child(2) .stat-value { color: #1e3a8a; }
        .stat:nth-child(3) .stat-value { color: #dc2626; }
        .stat-period { 
            background: #ecfdf5; 
            border: 1px solid #a7f3d0; 
            color: #047857; 
            font-weight: 600;
        }

        /* Filter Form Styles (Will be hidden on print) */
        .filter-form {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 25px;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        .filter-group { display: flex; flex-direction: column; }
        .filter-form label { font-weight: 600; color: #475569; margin-bottom: 5px; display: block; font-size: 14px; }
        .filter-form input[type="date"], .filter-form select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 150px; }
        .filter-form input[type="date"]:disabled, .filter-form select:disabled { background-color: #f1f5f9; cursor: not-allowed; color: #94a3b8; }
        .filter-form button { background: #3b82f6; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .filter-form button:hover:not(:disabled) { background: #2563eb; }
        .filter-form button:disabled { background: #93c5fd; cursor: not-allowed; }


        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 14px; }
        th { background: #eef2ff; color: #3730a3; font-weight: 600; text-transform: uppercase; font-size: 13px; }
        tr:hover { background-color: #f9fafb; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-weight: 600; font-size: 12px; text-transform: capitalize; }
        .status-approved, .status-ongoing { background-color: #e0f2fe; color: #075985; }
        .status-rejected { background-color: #ffe4e6; color: #9f1239; }
        .status-paid { background-color: #dcfce7; color: #047857; }

        .actions { display: flex; justify-content: center; gap: 20px; margin-top: 30px; }
        .actions button, .back {
            background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.3s; font-weight: 600; text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .actions button:hover, .back:hover { background: #1d4ed8; }
        .btn-print-pdf { background: #10b981; } /* Green for print */
        .btn-print-pdf:hover { background: #059669; }
        a.back { background: #475569; margin-top: 20px; display: block; width: fit-content; margin-left: auto; margin-right: auto; }
        a.back:hover { background: #334155; }

        /* --- Loading Overlay Styles --- */
        #loading-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.85); 
            display: none; 
            align-items: center; justify-content: center;
            z-index: 1000; border-radius: 16px;
        }
        .spinner {
            border: 4px solid #f3f3f3; border-top: 4px solid #3b82f6; 
            border-radius: 50%; width: 40px; height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        
        /* --- PRINT / PDF OPTIMIZATION --- */
        @media print {
            @page { 
                margin: 1.5cm; /* Mas malaking margin para sa PDF */
            }
            body { 
                background: white; 
                padding: 0; 
                color: #000;
                /* Font size adjustment for printing */
                font-size: 12pt; 
            }
            .container { 
                max-width: 100%;
                box-shadow: none; 
                margin: 0; 
                padding: 0;
            }
            /* Hide non-report elements */
            .filter-form, .actions, .back {
                display: none; 
            }
            #loading-overlay { display: none !important; }

            /* Report Header */
            h2 {
                color: #000;
                font-size: 24pt;
                border-bottom: 2px solid #ccc;
                padding-bottom: 5px;
            }
            h3 {
                color: #333;
                font-size: 14pt;
                margin-top: 20px;
                border-left: none; /* Remove blue border on print */
                padding-left: 0;
                border-bottom: 1px solid #eee;
            }

            /* Stats/KPI Boxes */
            .stats {
                gap: 10px;
                margin-bottom: 20px;
                page-break-inside: avoid; /* Keep stats together */
            }
            .stat {
                border: 1px solid #ccc;
                box-shadow: none;
                padding: 10px;
                /* Ensure background colors are printed (if allowed by browser) */
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                background: #f9f9f9; 
                min-width: 240px;
            }
            .stat-value { font-size: 18pt; color: #111; }
            .stat-label { color: #555; }
            .stat-period { background: #e6ffe6; } /* Lighter background for period stat */
            
            /* Table Print Styles */
            table { 
                margin-top: 10px; 
                border: 1px solid #ccc;
                page-break-inside: auto;
            }
            th { 
                background: #eee; 
                color: #000; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                border: 1px solid #ccc;
            }
            td {
                border: 1px solid #eee;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .status-badge {
                border: 1px solid #ccc;
                padding: 2px 8px;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
        }
        /* Responsive Table adjustments for mobile */
        @media (max-width: 900px) {
            .container { margin: 20px; padding: 20px; }
            .stats { justify-content: space-between; gap: 10px; }
            .stat { min-width: 48%; }
            .filter-form { flex-wrap: wrap; align-items: stretch; }
            .filter-group { width: 100%; }
            .filter-form input[type="date"], .filter-form select { width: 100%; }
            .filter-form button { width: 100%; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 0; }
            td { text-align: right; position: relative; padding-left: 55%; border-bottom: 1px dashed #f1f5f9; }
            td::before {
                content: attr(data-label); left: 15px; width: calc(55% - 30px); padding-right: 10px; white-space: nowrap; font-weight: 600; color: #475569; text-align: left; overflow: hidden; text-overflow: ellipsis;
                position: absolute;
            }
            tr:last-child td { border-bottom: none; }
        }
        @media (max-width: 500px) {
            .stats { flex-direction: column; }
            .stat { min-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <h2>📈 Comprehensive Financial Report (<?= ucfirst($user_role) ?> View)</h2>
    <p style="text-align: center; color: #64748b; margin-bottom: 15px;">**Generated by <?= htmlspecialchars($user_full_name) ?>**</p>

    <!-- Global KPIs (All Time) -->
    <h3 style="font-size: 20px; margin-top: 30px; margin-bottom: 15px; color: #1e3a8a;">📊 Key Performance Indicators (All-Time)</h3>
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Loans Issued</div>
            <div class="stat-value"><?= $total_loans ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Verified Payments</div>
            <div class="stat-value"><?= $total_payments_all_time ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Collected Amount</div>
            <div class="stat-value">₱<?= number_format($total_collected_all_time, 2) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Loans with Remaining Balance</div>
            <div class="stat-value"><?= number_format($loans_with_balance) ?> (<?= number_format($balance_percentage, 1) ?>%)</div>
        </div>
    </div>

    <?= $message ?>
    
    <!-- Date Filter Section (with Quick Select) -->
    <h3 style="font-size: 20px; margin-top: 30px; margin-bottom: 15px; color: #1e3a8a;">📅 Filter Verified Payments by Date</h3>
    <form method="get" class="filter-form" id="reportFilterForm">
        <!-- Quick Select Dropdown -->
        <div class="filter-group">
            <label for="range_preset">Quick Filter:</label>
            <select id="range_preset" name="range_preset" onchange="handlePresetChange(this.value)">
                <option value="last_30_days" <?= ($range_preset === 'last_30_days' ? 'selected' : '') ?>>Last 30 Days</option>
                <option value="last_7_days" <?= ($range_preset === 'last_7_days' ? 'selected' : '') ?>>Last 7 Days</option>
                <option value="this_month" <?= ($range_preset === 'this_month' ? 'selected' : '') ?>>This Month (<?= date('M') ?>)</option>
                <option value="last_month" <?= ($range_preset === 'last_month' ? 'selected' : '') ?>>Last Month (<?= date('M', strtotime('last month')) ?>)</option>
                <option value="custom" <?= ($range_preset === 'custom' ? 'selected' : '') ?>>Custom Range</option>
            </select>
        </div>
        
        <!-- Custom Date Inputs -->
        <div class="filter-group date-input-group">
            <label for="start_date">Start Date (Custom):</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date_safe) ?>" required <?= ($range_preset !== 'custom' ? 'disabled' : '') ?>>
        </div>
        <div class="filter-group date-input-group">
            <label for="end_date">End Date (Custom):</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date_safe) ?>" required <?= ($range_preset !== 'custom' ? 'disabled' : '') ?>>
        </div>
        
        <!-- Apply Button -->
        <button type="submit" id="filterButton">Apply Filter</button>
    </form>

    <!-- Filtered KPIs (Period Specific) -->
    <div class="stats">
        <div class="stat stat-period" style="flex: 1;">
            <div class="stat-label">Total Collected (<?= $filter_message ?>)</div>
            <div class="stat-value" style="color: #059669;">₱<?= number_format($total_collected_period, 2) ?></div>
        </div>
    </div>
    
    <!-- Filtered Payments Table (Sino ang bagong nagbayad) -->
    <h3 style="font-size: 20px; margin-top: 30px; margin-bottom: 15px; color: #1e3a8a;">💰 Verified Payments within Period</h3>
    <p style="color: #64748b; margin-bottom: 15px;"><?= $filter_message ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Payment Date</th>
                <th>Member Name</th>
                <th>Loan ID</th>
                <th>Amount Paid</th>
                <th>Method</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($filtered_payments)): ?>
                <?php foreach ($filtered_payments as $row): ?>
                    <tr>
                        <td data-label="Payment Date"><?= htmlspecialchars(date('M d, Y', strtotime($row['payment_date']))) ?></td>
                        <td data-label="Member Name"><?= htmlspecialchars($row['member_name']) ?></td>
                        <td data-label="Loan ID"><?= htmlspecialchars($row['loan_id']) ?></td>
                        <td data-label="Amount Paid" style="color: #059669;">₱<?= number_format($row['amount'], 2) ?></td>
                        <td data-label="Method"><?= htmlspecialchars($row['method']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No verified payments found within the selected date range.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <hr style="margin: 40px 0; border: 0; border-top: 2px dashed #e2e8f0;">

    <!-- Detailed Loan Status (All Loans) -->
    <h3 style="font-size: 20px; margin-top: 30px; margin-bottom: 15px; color: #1e3a8a;">💼 Detailed Loan Portfolio Status (All Active Loans)</h3>
    <table>
        <thead>
            <tr>
                <th>Member Name</th>
                <th>Loan Amount</th>
                <th>Total Paid (All Time)</th>
                <th>Remaining Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($details)): ?>
                <?php foreach ($details as $row): ?>
                    <tr>
                        <td data-label="Member Name"><?= htmlspecialchars($row['member_name']) ?></td>
                        <td data-label="Loan Amount">₱<?= number_format($row['loan_amount'], 2) ?></td>
                        <td data-label="Total Paid (All Time)" style="color: #059669;">₱<?= number_format($row['total_paid'], 2) ?></td>
                        <td data-label="Remaining Balance" style="color: <?= ($row['balance'] > 0) ? '#dc2626' : '#059669'; ?>;">
                            ₱<?= number_format(max(0, $row['balance']), 2) ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge status-<?= htmlspecialchars($row['loan_status']) ?>">
                                <?= htmlspecialchars(ucfirst($row['loan_status'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No loan data available.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Actions/Download Buttons (Now uses window.print() directly) -->
    <div class="actions">
        <button type="button" class="btn-print-pdf" onclick="window.print()">🖨️ View / Download PDF Report</button>
    </div>

    <a class="back" href="<?= htmlspecialchars($dashboard_link) ?>">← Back to <?= ucfirst($user_role) ?> Dashboard</a>
</div>

<script>
    /**
     * Toggles the disabled state of the custom date inputs based on the selected quick filter preset.
     * Only 'custom' selection enables the date inputs.
     */
    function handlePresetChange(selectedPreset) {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        if (selectedPreset === 'custom') {
            // Enable custom inputs
            startDateInput.disabled = false;
            endDateInput.disabled = false;
        } else {
            // Disable custom inputs
            startDateInput.disabled = true;
            endDateInput.disabled = true;
        }
    }

    // Call on page load to ensure initial state is correct (useful when refreshing a filtered report)
    document.addEventListener('DOMContentLoaded', () => {
        const presetSelect = document.getElementById('range_preset');
        const filterForm = document.getElementById('reportFilterForm');
        const loadingOverlay = document.getElementById('loading-overlay');
        
        // Initial setup for date inputs
        handlePresetChange(presetSelect.value);

        // Add event listener to the main filter form to show loading on submit
        // Since this reloads the page, the loading spinner will automatically be hidden
        // when the new page loads.
        filterForm.addEventListener('submit', () => {
            loadingOverlay.style.display = 'flex';
        });

        // Ensure the overlay is hidden if the page load was quick
        loadingOverlay.style.display = 'none';
    });

    // We no longer need showLoadingAndHideAfterDelay() since we are using window.print()
</script>

</body>
</html>
