<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

$q           = isset($_GET['q']) ? (string)$_GET['q'] : null;
$category_id = isset($_GET['category_id']) && ctype_digit($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$brand_id    = isset($_GET['brand_id']) && ctype_digit($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;

try {
    $results = get_pos_products($q, $category_id, $brand_id, 60);
    echo json_encode(['ok' => true, 'results' => $results]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
