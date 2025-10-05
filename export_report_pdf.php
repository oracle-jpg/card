<?php
require 'db.php';
require 'fpdf/fpdf.php';

// Fetch totals
$total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$total_payments = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$total_collected = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();

// Fetch detailed data
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

// Create PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

// ========== HEADER SECTION ==========
$pdf->Image('images/CMRBI-1.png', 10, 10, 25); // (file, x, y, width)
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'CARD RBI Microfinance System', 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Manager Report - Empowering Communities Through Microfinance', 0, 1, 'C');
$pdf->Cell(0, 8, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'C');
$pdf->Ln(10);

// ========== SUMMARY SECTION ==========
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Summary', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, "Total Loans: $total_loans", 0, 1);
$pdf->Cell(0, 8, "Total Payments: $total_payments", 0, 1);
$pdf->Cell(0, 8, "Total Collected: ₱" . number_format($total_collected, 2), 0, 1);
$pdf->Ln(10);

// ========== TABLE SECTION ==========
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(50, 8, 'Member Name', 1);
$pdf->Cell(30, 8, 'Loan (₱)', 1);
$pdf->Cell(30, 8, 'Paid (₱)', 1);
$pdf->Cell(30, 8, 'Balance (₱)', 1);
$pdf->Cell(30, 8, 'Status', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach ($details as $row) {
    $pdf->Cell(50, 8, utf8_decode($row['member_name']), 1);
    $pdf->Cell(30, 8, number_format($row['loan_amount'], 2), 1);
    $pdf->Cell(30, 8, number_format($row['total_paid'], 2), 1);
    $pdf->Cell(30, 8, number_format($row['balance'], 2), 1);
    $pdf->Cell(30, 8, $row['loan_status'], 1);
    $pdf->Ln();
}

// ========== FOOTER SECTION ==========
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, '© ' . date('Y') . ' CARD RBI - A Microfinance-Oriented Rural Bank', 0, 1, 'C');

// ========== OUTPUT ==========
$pdf->Output('I', 'Manager_Report_' . date('Ymd') . '.pdf');
exit;
?>
