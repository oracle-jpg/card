<?php
// Tiyakin na ito ang UNANG-UNANG LINE sa file. Walang space o newline sa taas.
session_start();
require_once 'auth.php';
require_once 'db.php';

$user = current_user();

// ONLY clients can view their own receipts/reports.
if ($user['role'] !== 'client') {
    header("Location: index.php");
    exit;
}

// Get linked member record
$stmt = $pdo->prepare("SELECT id, name FROM members WHERE user_id = ?");
$stmt->execute([$user['id']]);
$member = $stmt->fetch();

if (!$member) {
    die("❌ Error: Your account is not linked to any member record.");
}

// Fetch all VERIFIED payments for the client
$stmt = $pdo->prepare("
    SELECT p.*, l.amount AS loan_principal, l.interest_rate, l.term_months
    FROM payments p 
    JOIN loans l ON p.loan_id = l.id 
    WHERE l.member_id = ? AND p.status = 'verified'
    ORDER BY p.payment_date DESC
");
$stmt->execute([$member['id']]);
$payments = $stmt->fetchAll();

// Get Total Payments Made
$totalPaid = $pdo->prepare("
    SELECT SUM(amount) 
    FROM payments 
    WHERE loan_id IN (SELECT id FROM loans WHERE member_id = ?) 
    AND status = 'verified'
");
$totalPaid->execute([$member['id']]);
$totalPayments = $totalPaid->fetchColumn() ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#f8fafc; color:#1e293b; padding:40px; }
        .report-container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 { color:#1e3a8a; border-bottom: 2px solid #e0e7ff; padding-bottom: 10px; margin-bottom: 20px; text-align: center;}
        h2 { font-size: 18px; color: #334155; margin-top: 25px; margin-bottom: 10px; }
        .summary-box {
            display: flex;
            justify-content: space-between;
            background: #e0f2fe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .summary-item strong {
            display: block;
            font-size: 24px;
            color: #1e3a8a;
            margin-top: 5px;
        }

        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { padding:10px; border:1px solid #e2e8f0; text-align:left; font-size: 14px;}
        th { background:#f1f5f9; font-weight:600; color: #1e293b; }
        .no-data { text-align: center; padding: 20px; color: #64748b; }

        /* Print/Download Button Styles */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        .action-buttons button, .action-buttons a {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .print-btn { background: #10b981; color: white; }
        .print-btn:hover { background: #059669; }
        .back-btn { background: #475569; color: white; }
        .back-btn:hover { background: #334155; }


        /* Media Query for Print: Hides buttons and cleans up background for PDF */
        @media print {
            body { background: none; padding: 0; }
            .report-container { 
                box-shadow: none; 
                margin: 0; 
                padding: 0;
            }
            .action-buttons {
                display: none; /* Hide buttons when printing */
            }
            .summary-box {
                background: #f0f8ff; /* Lighter background for printing */
                border: 1px solid #cce5ff;
            }
        }
    </style>
</head>
<body>

<div class="report-container">
    <h1>Payment History Report</h1>

    <div class="summary-box">
        <div class="summary-item">
            Client Name: <strong><?= htmlspecialchars($member['name']) ?></strong>
        </div>
        <div class="summary-item">
            Report Date: <strong><?= date('M d, Y') ?></strong>
        </div>
        <div class="summary-item">
            Total Payments Made: <strong>₱<?= number_format($totalPayments, 2) ?></strong>
        </div>
    </div>
    
    <h2>Transaction Details</h2>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Loan ID</th>
                <th>Amount Paid (₱)</th>
                <th>Method</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($payments): ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($p['payment_date']))) ?></td>
                        <td><?= htmlspecialchars($p['loan_id']) ?></td>
                        <td><?= number_format($p['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['method']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="no-data">No verified payments found to generate report.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="action-buttons">
        <!-- Ito ang mag-trigger ng print dialog na may option na 'Save as PDF' -->
        <button onclick="window.print()" class="print-btn">🖨️ View / Download PDF</button>
        <a href="my_payments.php" class="back-btn">⬅ Back to Payments</a>
    </div>
</div>

</body>
</html>
