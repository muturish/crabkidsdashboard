<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

// Sends an M-Pesa STK push (payment prompt) through the POS.
// POST JSON: {"phone": "0712 345 678", "amount": 3500}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$phone  = normalize_ke_phone((string)($in['phone'] ?? ''));
$amount = $in['amount'] ?? null;

if (!$phone) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Enter a valid Safaricom number, e.g. 0712 345 678.']);
    exit;
}
// M-Pesa only takes whole shillings, KES 1 - 250,000 per transaction.
if (!is_numeric($amount) || (float)$amount != floor((float)$amount) || $amount < 1 || $amount > 250000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'The amount must be a whole number of shillings between KES 1 and KES 250,000.']);
    exit;
}

try {
    $r = pos_stk_push($phone, (int)$amount);
    echo json_encode(['ok' => true, 'checkout_request_id' => $r['checkout_request_id'], 'phone' => $phone]);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
