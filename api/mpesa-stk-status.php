<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

// Status of an STK push sent via api/mpesa-stk.php: pending | success | failed.
$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' || strlen($id) > 100) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing checkout request id.']);
    exit;
}

try {
    $t = get_stk_status($id);
    if (!$t) {
        echo json_encode(['ok' => true, 'status' => 'pending']);
        exit;
    }
    $status = $t['status'] === 'success' && $t['receipt_number'] ? 'success' : ($t['status'] === 'failed' ? 'failed' : 'pending');
    echo json_encode([
        'ok'             => true,
        'status'         => $status,
        'receipt_number' => $t['receipt_number'],
        'amount'         => $t['amount'],
        'first_name'     => $t['first_name'],
        'result_desc'    => $t['result_desc'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
