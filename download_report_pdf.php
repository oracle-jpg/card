<?php
session_start();
require 'db.php';
// We assume TCPDF library is available via Composer/require if running locally.
// For Canvas context, we mock the PDF generation process.

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

// --- Data Fetching (Same as reports.php) ---
$total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$total_payments = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$total_collected = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();

// Loans with Remaining Balance (Risk Metric)
$total_active_loans_query = "
    SELECT COUNT(t.loan_id) FROM (
        SELECT 
            l.id AS loan_id
        FROM loans l 
        LEFT JOIN payments p ON p.loan_id = l.id
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

// Loan details
$query = "
SELECT m.name AS member_name, 
        l.amount AS loan_amount,
        l.status AS loan_status,
        IFNULL(SUM(p.amount), 0) AS total_paid,
        (l.amount - IFNULL(SUM(p.amount),0)) AS balance
FROM loans l
JOIN members m ON l.member_id = m.id
LEFT JOIN payments p ON p.loan_id = l.id
GROUP BY l.id
ORDER BY m.name ASC
";
$details = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// --- PDF Content Generation Simulation (Same HTML content) ---
$date_generated = date('Y-m-d'); // Use date only for filename
$datetime_generated = date('M d, Y H:i:s'); // Use full date for document content

$html_content = '
<h1>MICROFINANCE LOAN PORTFOLIO REPORT</h1>
<p style="font-size: 10pt; color: #555;">Report Date: ' . $datetime_generated . '</p>
<hr>

<h3 style="color: #1e3a8a;">I. Key Performance Indicators (KPIs)</h3>
<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 10pt;">
    <tr>
        <td style="width: 30%; background-color: #f1f5f9; font-weight: bold;">Total Loans Issued</td>
        <td style="width: 20%;">' . number_format($total_loans) . '</td>
        <td style="width: 30%; background-color: #f1f5f9; font-weight: bold;">Total Collected Amount</td>
        <td style="width: 20%;">₱' . number_format($total_collected, 2) . '</td>
    </tr>
    <tr>
        <td style="width: 30%; background-color: #f1f5f9; font-weight: bold;">Total Payments Recorded</td>
        <td style="width: 20%;">' . number_format($total_payments) . '</td>
        <td style="width: 30%; background-color: #fef9c3; font-weight: bold; color: #854d0e;">Loans with Remaining Balance (Risk)</td>
        <td style="width: 20%; color: #854d0e;">' . number_format($loans_with_balance) . ' (' . number_format($balance_percentage, 1) . '%)</td>
    </tr>
</table>

<br><br>

<h3 style="color: #1e3a8a;">II. Detailed Loan Listing</h3>
<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 9pt;">
    <thead>
        <tr style="background-color: #e0f2fe; color: #1e3a8a; font-weight: bold;">
            <th style="width: 25%;">Member Name</th>
            <th style="width: 15%;">Loan Amount</th>
            <th style="width: 15%;">Total Paid</th>
            <th style="width: 15%;">Balance</th>
            <th style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

foreach ($details as $row) {
    $status_color = '#dcfce7'; // Default for Approved/Ongoing
    if ($row['loan_status'] == 'rejected') $status_color = '#fee2e2';
    if ($row['loan_status'] == 'paid') $status_color = '#bfdbfe';

    $html_content .= '
    <tr>
        <td>' . htmlspecialchars($row['member_name']) . '</td>
        <td>₱' . number_format($row['loan_amount'], 2) . '</td>
        <td>₱' . number_format($row['total_paid'], 2) . '</td>
        <td>₱' . number_format($row['balance'], 2) . '</td>
        <td style="background-color: ' . $status_color . ';">' . htmlspecialchars(ucfirst($row['loan_status'])) . '</td>
    </tr>';
}

$html_content .= '
    </tbody>
</table>';

// --- TCPDF Simulation Headers (For download) ---
$filename = "Portfolio_Report_" . $date_generated . ".html"; // Since we can't output a real PDF, we output an HTML file with download headers
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the HTML content
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Loan Portfolio PDF Preview</title><style>body { font-family: sans-serif; margin: 30px; line-height: 1.6; } h1 { color: #1e3a8a; text-align: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; } h3 { margin-top: 20px; color: #1e3a8a; } table { width: 100%; border-collapse: collapse; margin-top: 15px; } th, td { padding: 8px; border: 1px solid #ccc; text-align: left; } .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 8pt; color: #777; }</style></head><body>' . $html_content . '</body></html>';
exit;
?>
