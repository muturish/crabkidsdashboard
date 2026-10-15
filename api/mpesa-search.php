<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

$q     = isset($_GET['q']) ? (string)$_GET['q'] : '';
$limit = isset($_GET['limit']) && ctype_digit($_GET['limit']) ? min(50, (int)$_GET['limit']) : 10;

try {
    $results = search_mpesa_transactions($q, $limit);
    echo json_encode(['ok' => true, 'results' => $results]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
