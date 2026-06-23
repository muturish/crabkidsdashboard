<?php
/**
 * Data access layer for the Stock Dashboard.
 *
 * Schema notes (UltimatePOS):
 * - `transactions` with type='purchase' + status='received'  -> confirmed stock-in headers
 * - `purchase_lines`                                          -> stock-in quantities per variation
 * - `transactions` with type='sell' + status='final'          -> confirmed sales headers
 * - `transaction_sell_lines`                                  -> stock-out quantities per variation
 * - `stock_adjustment_lines`                                  -> manual +/- corrections (qty can be negative)
 * - `variation_location_details.qty_available`                -> current snapshot of stock on hand
 * - `variations` -> `products` -> `categories`                 -> product naming/grouping
 *
 * All functions are read-only SELECT queries.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Top KPI cards: current stock on hand, stock value, items received and
 * sold in the selected period, and net stock change in that period.
 */
function get_overview_kpis(PDO $pdo, int $businessId, string $fromDate, string $toDate): array
{
    // Current total units on hand (live snapshot, not date-filtered —
    // this is "right now", regardless of the selected period).
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(vld.qty_available), 0) AS total_units,
            COALESCE(SUM(vld.qty_available * v.default_purchase_price), 0) AS stock_value_cost,
            COALESCE(SUM(vld.qty_available * v.default_sell_price), 0) AS stock_value_retail
        FROM variation_location_details vld
        JOIN variations v ON v.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id
        WHERE p.business_id = :business_id
          AND p.is_inactive = 0
    ");
    $stmt->execute(['business_id' => $businessId]);
    $stockNow = $stmt->fetch();

    // Units received via purchases within the period
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pl.quantity), 0) AS units_in
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
        WHERE t.business_id = :business_id
          AND t.type = 'purchase'
          AND t.status = 'received'
          AND t.transaction_date BETWEEN :from_date AND :to_date
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $unitsIn = (float) $stmt->fetch()['units_in'];

    // Units sold within the period
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(tsl.quantity - tsl.quantity_returned), 0) AS units_out
        FROM transaction_sell_lines tsl
        JOIN transactions t ON t.id = tsl.transaction_id
        WHERE t.business_id = :business_id
          AND t.type = 'sell'
          AND t.status = 'final'
          AND t.transaction_date BETWEEN :from_date AND :to_date
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $unitsOut = (float) $stmt->fetch()['units_out'];

    // Net manual stock adjustments within the period (can be +/-)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(sal.quantity), 0) AS net_adjustment
        FROM stock_adjustment_lines sal
        JOIN transactions t ON t.id = sal.transaction_id
        WHERE t.business_id = :business_id
          AND t.transaction_date BETWEEN :from_date AND :to_date
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $netAdjustment = (float) $stmt->fetch()['net_adjustment'];

    return [
        'total_units_on_hand' => (float) $stockNow['total_units'],
        'stock_value_cost'    => (float) $stockNow['stock_value_cost'],
        'stock_value_retail'  => (float) $stockNow['stock_value_retail'],
        'units_in'            => $unitsIn,
        'units_out'           => $unitsOut,
        'net_adjustment'      => $netAdjustment,
        'net_stock_change'    => $unitsIn - $unitsOut + $netAdjustment,
    ];
}

/**
 * Daily stock movement series for charting: units received, units sold,
 * net adjustment, and running cumulative net change, between two dates.
 */
function get_stock_growth_series(PDO $pdo, int $businessId, string $fromDate, string $toDate): array
{
    $stmt = $pdo->prepare("
        SELECT DATE(t.transaction_date) AS day, COALESCE(SUM(pl.quantity), 0) AS units_in
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
        WHERE t.business_id = :business_id
          AND t.type = 'purchase'
          AND t.status = 'received'
          AND t.transaction_date BETWEEN :from_date AND :to_date
        GROUP BY DATE(t.transaction_date)
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $inByDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $inByDay[$row['day']] = (float) $row['units_in'];
    }

    $stmt = $pdo->prepare("
        SELECT DATE(t.transaction_date) AS day,
               COALESCE(SUM(tsl.quantity - tsl.quantity_returned), 0) AS units_out
        FROM transaction_sell_lines tsl
        JOIN transactions t ON t.id = tsl.transaction_id
        WHERE t.business_id = :business_id
          AND t.type = 'sell'
          AND t.status = 'final'
          AND t.transaction_date BETWEEN :from_date AND :to_date
        GROUP BY DATE(t.transaction_date)
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $outByDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $outByDay[$row['day']] = (float) $row['units_out'];
    }

    $stmt = $pdo->prepare("
        SELECT DATE(t.transaction_date) AS day, COALESCE(SUM(sal.quantity), 0) AS net_adjustment
        FROM stock_adjustment_lines sal
        JOIN transactions t ON t.id = sal.transaction_id
        WHERE t.business_id = :business_id
          AND t.transaction_date BETWEEN :from_date AND :to_date
        GROUP BY DATE(t.transaction_date)
    ");
    $stmt->execute(['business_id' => $businessId, 'from_date' => $fromDate, 'to_date' => $toDate]);
    $adjByDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $adjByDay[$row['day']] = (float) $row['net_adjustment'];
    }

    // Build a continuous day-by-day series so the chart has no gaps
    $series = [];
    $cursor = new DateTime(substr($fromDate, 0, 10));
    $end = new DateTime(substr($toDate, 0, 10));
    $running = 0.0;

    while ($cursor <= $end) {
        $day = $cursor->format('Y-m-d');
        $in = $inByDay[$day] ?? 0.0;
        $out = $outByDay[$day] ?? 0.0;
        $adj = $adjByDay[$day] ?? 0.0;
        $net = $in - $out + $adj;
        $running += $net;

        $series[] = [
            'date'        => $day,
            'units_in'    => $in,
            'units_out'   => $out,
            'adjustment'  => $adj,
            'net_change'  => $net,
            'running_total' => $running,
        ];

        $cursor->modify('+1 day');
    }

    return $series;
}

/**
 * Stock on hand broken down by category, for a bar/pie chart.
 */
function get_stock_by_category(PDO $pdo, int $businessId): array
{
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(c.name, 'Uncategorized') AS category_name,
            COALESCE(SUM(vld.qty_available), 0) AS total_units,
            COALESCE(SUM(vld.qty_available * v.default_purchase_price), 0) AS stock_value_cost
        FROM variation_location_details vld
        JOIN variations v ON v.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.business_id = :business_id
          AND p.is_inactive = 0
        GROUP BY c.id, c.name
        ORDER BY total_units DESC
    ");
    $stmt->execute(['business_id' => $businessId]);
    return $stmt->fetchAll();
}

/**
 * Top products by units received (restocked) within the period —
 * shows what's actively growing in stock.
 */
function get_top_restocked_products(PDO $pdo, int $businessId, string $fromDate, string $toDate, int $limit = 10): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.name AS product_name,
            p.sku AS product_sku,
            COALESCE(SUM(pl.quantity), 0) AS units_in,
            COALESCE(SUM(pl.quantity * pl.purchase_price), 0) AS amount_spent
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
        JOIN products p ON p.id = pl.product_id
        WHERE t.business_id = :business_id
          AND t.type = 'purchase'
          AND t.status = 'received'
          AND t.transaction_date BETWEEN :from_date AND :to_date
        GROUP BY p.id, p.name, p.sku
        ORDER BY units_in DESC
        LIMIT :limit
    ");
    $stmt->bindValue('business_id', $businessId, PDO::PARAM_INT);
    $stmt->bindValue('from_date', $fromDate);
    $stmt->bindValue('to_date', $toDate);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Products at or below their alert quantity — the low-stock alert list.
 * Sums qty_available across all locations per variation.
 */
function get_low_stock_items(PDO $pdo, int $businessId, int $limit = 200): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id AS product_id,
            p.name AS product_name,
            p.sku AS product_sku,
            v.id AS variation_id,
            v.name AS variation_name,
            v.sub_sku AS variation_sku,
            p.alert_quantity,
            COALESCE(SUM(vld.qty_available), 0) AS qty_available,
            c.name AS category_name
        FROM variations v
        JOIN products p ON p.id = v.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN variation_location_details vld ON vld.variation_id = v.id
        WHERE p.business_id = :business_id
          AND p.is_inactive = 0
          AND p.enable_stock = 1
          AND p.alert_quantity IS NOT NULL
        GROUP BY v.id, p.id, p.name, p.sku, v.name, v.sub_sku, p.alert_quantity, c.name
        HAVING qty_available <= p.alert_quantity
        ORDER BY qty_available ASC
        LIMIT :limit
    ");
    $stmt->bindValue('business_id', $businessId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Items that are completely out of stock (qty_available <= 0),
 * regardless of whether an alert_quantity is configured.
 */
function get_out_of_stock_items(PDO $pdo, int $businessId, int $limit = 200): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id AS product_id,
            p.name AS product_name,
            p.sku AS product_sku,
            v.id AS variation_id,
            v.name AS variation_name,
            v.sub_sku AS variation_sku,
            c.name AS category_name,
            COALESCE(SUM(vld.qty_available), 0) AS qty_available
        FROM variations v
        JOIN products p ON p.id = v.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN variation_location_details vld ON vld.variation_id = v.id
        WHERE p.business_id = :business_id
          AND p.is_inactive = 0
          AND p.enable_stock = 1
        GROUP BY v.id, p.id, p.name, p.sku, v.name, v.sub_sku, c.name
        HAVING qty_available <= 0
        ORDER BY p.name ASC
        LIMIT :limit
    ");
    $stmt->bindValue('business_id', $businessId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * List of business locations, for the location filter dropdown
 * (kept here for future use even though the dashboard defaults to "all").
 */
function get_locations(PDO $pdo, int $businessId): array
{
    $stmt = $pdo->prepare("
        SELECT id, name
        FROM business_locations
        WHERE business_id = :business_id
        ORDER BY name ASC
    ");
    $stmt->execute(['business_id' => $businessId]);
    return $stmt->fetchAll();
}
