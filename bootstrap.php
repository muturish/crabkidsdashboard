<?php
/**
 * Shared bootstrap: error display, auth gate, DB connection, data layer.
 * Every page in this project requires this file first.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1'); // turn off in production

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

require_dashboard_login();

$pdo = get_db();
$bizId = business_id();

/**
 * Resolve the date range for the dashboard from query params,
 * defaulting to the last 30 days.
 */
function resolve_date_range(): array
{
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to   = $_GET['to'] ?? date('Y-m-d');

    // Basic validation — fall back to defaults if malformed
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-d', strtotime('-30 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }

    return [$from, $to . ' 23:59:59'];
}

function format_number(float $n, int $decimals = 0): string
{
    return number_format($n, $decimals);
}

function format_kes(float $n): string
{
    return 'KSh ' . number_format($n, 0);
}
