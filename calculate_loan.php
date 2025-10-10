<?php
// calculate_loan.php
// Endpoint to calculate loan interest and total payable dynamically.
require 'db.php';

header('Content-Type: application/json');

// Define your fixed interest rate parameters
// You can make these dynamic by fetching from a 'settings' table if needed.
const MIN_RATE = 3.0; // 3%
const MAX_RATE = 5.0; // 5%
const MIN_TERM = 1; // 1 month
const MAX_TERM = 12; // 12 months

// --- Helper Functions (Copied from client_dashboard.php for consistency) ---
function calculateTotalPayable($principal, $rate, $term_months) {
    // Standard Simple Interest Calculation
    $term_in_years = $term_months / 12;
    $interest_amount = $principal * ($rate / 100) * $term_in_years;
    return [
        'total_payable' => round($principal + $interest_amount, 2),
        'interest_rate' => $rate,
        'interest_amount' => round($interest_amount, 2)
    ];
}

// Function to determine the interest rate based on loan term (Linear Interpolation)
function getDynamicRate($term_months) {
    if ($term_months <= MIN_TERM) return MIN_RATE;
    if ($term_months >= MAX_TERM) return MAX_RATE;

    // Linear interpolation: Rate = MIN_RATE + (MAX_RATE - MIN_RATE) * ((TERM - MIN_TERM) / (MAX_TERM - MIN_TERM))
    $rate_range = MAX_RATE - MIN_RATE;
    $term_range = MAX_TERM - MIN_TERM;
    $term_ratio = ($term_months - MIN_TERM) / $term_range;

    return round(MIN_RATE + ($rate_range * $term_ratio), 2);
}
// --------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// 1. Validate Input
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$term = filter_input(INPUT_POST, 'term', FILTER_VALIDATE_INT);

if (!$amount || $amount <= 0 || !$term || $term < MIN_TERM || $term > MAX_TERM) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount or term provided.']);
    exit;
}

// 2. Determine Rate and Calculate
$final_rate = getDynamicRate($term);
$calculation = calculateTotalPayable($amount, $final_rate, $term);

// 3. Return JSON Result
echo json_encode([
    'success' => true,
    'principal' => number_format($amount, 2),
    'term_months' => $term,
    'interest_rate' => $final_rate . '%',
    'interest_amount' => number_format($calculation['interest_amount'], 2),
    'total_payable' => number_format($calculation['total_payable'], 2),
]);

?>
