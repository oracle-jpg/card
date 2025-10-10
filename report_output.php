<?php
// Report Viewer and Downloader (Unified File)
// This file handles both the printable HTML output (view) and forces a download (download)
// by checking the 'mode' parameter.

require_once 'auth.php'; 
require_once 'db.php';
require_role(['staff', 'manager', 'admin']); 
$user = current_user();

// --- Configuration and Data Fetching Logic (Identical to generate_report.php) ---

// Helper function for currency formatting
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Helper function to process dates based on preset
function calculateDates($preset, $start_input, $end_input) {
    $default_start_date = date('Y-m-d', strtotime('-30 days'));
    $default_end_date = date('Y-m-d');

    switch ($preset) {
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
            $start_date = $start_input ?: $default_start_date;
            $end_date = $end_input ?: $default_end_date;
            break;
        case 'last_30_days':
        default:
            $start_date = $default_start_date;
            $end_date = $default_end_date;
            break;
    }
    
    $start_date_safe = date('Y-m-d', strtotime($start_date));
    $end_date_safe = date('Y-m-d', strtotime($end_date));
    $end_date_safe_inclusive = $end_date_safe . ' 23:59:59';
    
    return [
        'start_date' => $start_date_safe,
        'end_date' => $end_date_safe,
        'end_date_inclusive' => $end_date_safe_inclusive,
    ];
}

// Get filter selections from URL
$range_preset = $_GET['range_preset'] ?? 'last_30_days';
$start_date_input = $_GET['start_date'] ?? null;
$end_date_input = $_GET['end_date'] ?? null;
$mode = $_GET['mode'] ?? 'view'; // Check for the mode: 'view' (default) or 'download'

$dates = calculateDates($range_preset, $start_date_input, $end_date_input);
extract($dates); // $start_date, $end_date, $end_date_inclusive

$filter_message = "from " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));

// --- Database Queries ---
try {
    // 1. Total Collected for Filtered Period (Filtered KPI)
    $total_collected_filtered = $pdo->prepare("
        SELECT SUM(amount) FROM payments 
        WHERE status = 'verified' AND payment_date BETWEEN :start_date AND :end_date_inclusive
    ");
    $total_collected_filtered->execute([
        'start_date' => $start_date,
        'end_date_inclusive' => $end_date_inclusive
    ]);
    $total_collected_period = $total_collected_filtered->fetchColumn() ?? 0;
    
    // 2. FILTERED PAYMENTS (Detailed List for Date Range)
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
        'start_date' => $start_date,
        'end_date_inclusive' => $end_date_inclusive
    ]);
    $filtered_payments = $stmt_payments->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Detailed LOAN Status (All Loans, grouped by ID)
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
    $loan_details = $pdo->query($loan_details_query)->fetchAll(PDO::FETCH_ASSOC);

    // 4. Global KPIs
    $total_collected_all_time = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;
    $loans_with_balance = $pdo->query("
        SELECT COUNT(t.loan_id) FROM (
            SELECT l.id AS loan_id FROM loans l 
            LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'verified'
            WHERE l.status IN ('approved', 'ongoing')
            GROUP BY l.id, l.amount
            HAVING (l.amount - IFNULL(SUM(p.amount), 0)) > 0
        ) AS t
    ")->fetchColumn() ?? 0;
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- Set HTTP Headers based on Mode ---
if ($mode === 'download') {
    // Set headers to force download (assuming a PDF library would process this HTML)
    $filename = 'Financial_Report_' . date('Ymd_His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
}
// If $mode is 'view', the script continues to render the HTML.


// --- HTML Generation for PDF Layout ---
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loan Management Financial Report - <?= date('Y-m-d') ?></title>
    <!-- Use basic CSS for clean PDF rendering (often inline or simple external CSS is best) -->
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; font-size: 10pt; line-height: 1.4; }
        .report-header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #ccc; }
        .report-header h1 { font-size: 18pt; color: #1e3a8a; margin: 0; }
        .report-header p { font-size: 10pt; color: #666; margin: 5px 0 0 0; }
        .section-title { font-size: 14pt; color: #1e3a8a; margin: 20px 0 10px 0; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; page-break-inside: auto; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 9pt; }
        th { background-color: #f0f4f8; color: #1e293b; font-weight: bold; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        .summary-box { 
            background-color: #ecfdf5; 
            border: 1px solid #a7f3d0; 
            padding: 15px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-size: 12pt;
            color: #047857;
            border-radius: 5px;
            font-weight: bold;
        }
        .summary-box span { font-size: 14pt; color: #065f46; }
        .footer { 
            position: fixed; 
            bottom: 0; 
            width: 100%; 
            text-align: right; 
            font-size: 8pt; 
            color: #999;
            padding: 5px 0;
        }
        
        /* Styles for the Download Button (Only visible in 'view' mode in browser) */
        .download-bar {
            background-color: #1e3a8a; 
            color: white; 
            padding: 10px 20px; 
            text-align: right; 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0;
            z-index: 100;
        }
        .download-bar a {
            background-color: #10b981; 
            color: white; 
            text-decoration: none; 
            padding: 8px 15px; 
            border-radius: 4px; 
            font-size: 10pt;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .download-bar a:hover {
            background-color: #059669;
        }
        
        /* Adjust body padding so content isn't hidden under the fixed bar */
        <?php if ($mode === 'view'): ?>
        body { padding-top: 50px; }
        <?php endif; ?>

        /* Hide the bar when printing */
        @media print {
            .download-bar { display: none; }
            body { padding-top: 0; }
        }
    </style>
</head>
<body>

<?php 
// Show download button only when viewing the report in the browser
if ($mode === 'view'): 
    // Re-create the URL parameters for the download link
    $download_params = http_build_query([
        'range_preset' => $range_preset,
        'start_date' => $start_date_input,
        'end_date' => $end_date_input,
        'mode' => 'download'
    ]);
?>
<div class="download-bar">
    <a href="report_output.php?<?= htmlspecialchars($download_params) ?>">📥 Download as PDF</a>
</div>
<?php endif; ?>

<div class="report-header">
    <h1>Comprehensive Financial Report</h1>
    <p>Generated by: <?= htmlspecialchars($user['full_name'] ?? 'Admin') ?> (<?= ucfirst($user['role'] ?? 'staff') ?>) on <?= date('M d, Y') ?></p>
</div>

<!-- Global KPIs Summary -->
<div class="section-title" style="margin-top: 5px;">Overall Performance Metrics</div>
<table>
    <thead>
        <tr>
            <th>Total Collected (All Time)</th>
            <th>Loans with Remaining Balance</th>
            <th>Report Date Range</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= formatCurrency($total_collected_all_time) ?></td>
            <td><?= number_format($loans_with_balance) ?> (Risk Check)</td>
            <td><?= $filter_message ?></td>
        </tr>
    </tbody>
</table>

<!-- Filtered Period Summary -->
<div class="section-title">Collection Summary for Period: <?= $filter_message ?></div>
<div class="summary-box">
    Total Verified Amount Collected: <span><?= formatCurrency($total_collected_period) ?></span>
</div>

<!-- Detailed Payments Table -->
<div class="section-title">Detailed Verified Payments (<?= $filter_message ?>)</div>
<table>
    <thead>
        <tr>
            <th style="width: 15%;">Payment Date</th>
            <th style="width: 35%;">Member Name</th>
            <th style="width: 10%;">Loan ID</th>
            <th style="width: 20%; text-align: right;">Amount Paid</th>
            <th style="width: 20%;">Method</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($filtered_payments)): ?>
            <?php foreach ($filtered_payments as $row): ?>
                <tr>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['payment_date']))) ?></td>
                    <td><?= htmlspecialchars($row['member_name']) ?></td>
                    <td><?= htmlspecialchars($row['loan_id']) ?></td>
                    <td style="text-align: right;"><?= formatCurrency($row['amount']) ?></td>
                    <td><?= htmlspecialchars($row['method']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">No verified payments found within this date range.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Detailed Loan Status Table -->
<div class="section-title">Detailed Loan Portfolio Status</div>
<table>
    <thead>
        <tr>
            <th style="width: 30%;">Member Name</th>
            <th style="width: 20%; text-align: right;">Loan Amount</th>
            <th style="width: 20%; text-align: right;">Total Paid</th>
            <th style="width: 20%; text-align: right;">Remaining Balance</th>
            <th style="width: 10%;">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($loan_details)): ?>
            <?php foreach ($loan_details as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['member_name']) ?></td>
                    <td style="text-align: right;"><?= formatCurrency($row['loan_amount']) ?></td>
                    <td style="text-align: right; color: #059669;"><?= formatCurrency($row['total_paid']) ?></td>
                    <td style="text-align: right; color: <?= ($row['balance'] > 0) ? '#dc2626' : '#059669'; ?>;">
                        <?= formatCurrency(max(0, $row['balance'])) ?>
                    </td>
                    <td><?= htmlspecialchars(ucfirst($row['loan_status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">No loan data available.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Removed footer for cleaner PDF output, as the web preview will use the top bar -->

</body>
</html>
