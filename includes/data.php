<?php
// ── KPI cards ─────────────────────────────────────────────────────────────────

function get_kpis(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("SELECT COUNT(*) FROM products WHERE business_id = :bid AND is_inactive = 0");
    $st->execute([':bid' => $bid]);
    $total_products = (int)$st->fetchColumn();

    $st = $db->prepare("
        SELECT COALESCE(SUM(vld.qty_available), 0)
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        WHERE vld.qty_available > 0
    ");
    $st->execute([':bid' => $bid]);
    $total_units = (float)$st->fetchColumn();

    // Stock cost value: latest purchase price per variation × qty available
    $st = $db->prepare("
        SELECT COALESCE(SUM(vld.qty_available * pl_latest.purchase_price_inc_tax), 0)
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        JOIN (
            SELECT pl.variation_id, pl.purchase_price_inc_tax
            FROM purchase_lines pl
            JOIN transactions t ON t.id = pl.transaction_id
                AND t.type = 'purchase' AND t.status = 'received' AND t.business_id = :bid2
            WHERE pl.id = (
                SELECT pl2.id FROM purchase_lines pl2
                JOIN transactions t2 ON t2.id = pl2.transaction_id
                    AND t2.type = 'purchase' AND t2.status = 'received'
                WHERE pl2.variation_id = pl.variation_id
                ORDER BY t2.transaction_date DESC LIMIT 1
            )
        ) pl_latest ON pl_latest.variation_id = vld.variation_id
        WHERE vld.qty_available > 0
    ");
    $st->execute([':bid' => $bid, ':bid2' => $bid]);
    $stock_cost_value = (float)$st->fetchColumn();

    // Sell-price value
    $st = $db->prepare("
        SELECT COALESCE(SUM(vld.qty_available * pv.default_sell_price), 0)
        FROM variation_location_details vld
        JOIN variations pv ON pv.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        WHERE vld.qty_available > 0
    ");
    $st->execute([':bid' => $bid]);
    $stock_sell_value = (float)$st->fetchColumn();

    $st = $db->prepare("
        SELECT COUNT(DISTINCT vld.variation_id)
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        WHERE vld.qty_available > 0 AND p.alert_quantity IS NOT NULL AND vld.qty_available <= p.alert_quantity
    ");
    $st->execute([':bid' => $bid]);
    $low_stock = (int)$st->fetchColumn();

    $st = $db->prepare("
        SELECT COUNT(DISTINCT vld.variation_id)
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        WHERE vld.qty_available <= 0
    ");
    $st->execute([':bid' => $bid]);
    $out_of_stock = (int)$st->fetchColumn();

    // Today's sales
    $st = $db->prepare("
        SELECT COALESCE(SUM(final_total), 0)
        FROM transactions
        WHERE business_id = :bid AND type = 'sell' AND status = 'final'
          AND DATE(transaction_date) = CURDATE()
    ");
    $st->execute([':bid' => $bid]);
    $today_sales = (float)$st->fetchColumn();

    return compact('total_products','total_units','stock_cost_value','stock_sell_value','low_stock','out_of_stock','today_sales');
}

// ── Daily stock movement (units received vs sold) ─────────────────────────────

function get_daily_stock_movement(string $from, string $to): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day, COALESCE(SUM(pl.quantity), 0) AS qty
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
            AND t.type = 'purchase' AND t.status = 'received'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        GROUP BY day
    ");
    $st->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    $recv = array_column($st->fetchAll(), 'qty', 'day');

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day,
               COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        GROUP BY day
    ");
    $st->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    $sold = array_column($st->fetchAll(), 'qty', 'day');

    $rows = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);
    $cumul  = 0;
    while ($cursor <= $end) {
        $d      = $cursor->format('Y-m-d');
        $r      = (float)($recv[$d] ?? 0);
        $s      = (float)($sold[$d] ?? 0);
        $net    = $r - $s;
        $cumul += $net;
        $rows[] = ['day' => $d, 'received' => $r, 'sold' => $s, 'net' => $net, 'cumulative' => $cumul];
        $cursor->modify('+1 day');
    }
    return $rows;
}

// ── Daily sales revenue ───────────────────────────────────────────────────────

function get_daily_revenue(string $from, string $to): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT DATE(transaction_date) AS day,
               COALESCE(SUM(final_total), 0) AS revenue,
               COUNT(*) AS orders
        FROM transactions
        WHERE business_id = :bid AND type = 'sell' AND status = 'final'
          AND DATE(transaction_date) BETWEEN :from AND :to
        GROUP BY day
        ORDER BY day
    ");
    $st->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);

    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[$r['day']] = ['revenue' => $r['revenue'], 'orders' => $r['orders']];
    }

    $rows   = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);
    while ($cursor <= $end) {
        $d      = $cursor->format('Y-m-d');
        $rows[] = [
            'day'     => $d,
            'revenue' => (float)($map[$d]['revenue'] ?? 0),
            'orders'  => (int)($map[$d]['orders']  ?? 0),
        ];
        $cursor->modify('+1 day');
    }
    return $rows;
}

// ── Stock by category ─────────────────────────────────────────────────────────

function get_stock_by_category(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT c.name AS category,
               SUM(vld.qty_available) AS total_qty
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        JOIN categories c ON c.id = p.category_id
        WHERE vld.qty_available > 0
        GROUP BY c.id, c.name
        ORDER BY total_qty DESC
    ");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

// ── Top products by stock value ───────────────────────────────────────────────

function get_top_stock_value(int $limit = 10): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT p.name AS product,
               SUM(vld.qty_available) AS qty,
               MAX(pl.purchase_price_inc_tax) AS cost_price,
               SUM(vld.qty_available * pl.purchase_price_inc_tax) AS stock_value
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        JOIN purchase_lines pl ON pl.variation_id = vld.variation_id
        JOIN transactions t ON t.id = pl.transaction_id
            AND t.type = 'purchase' AND t.status = 'received' AND t.business_id = :bid2
        WHERE vld.qty_available > 0
        GROUP BY p.id
        ORDER BY stock_value DESC
        LIMIT :lim
    ");
    $st->bindValue(':bid',  $bid,   PDO::PARAM_INT);
    $st->bindValue(':bid2', $bid,   PDO::PARAM_INT);
    $st->bindValue(':lim',  $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Low stock ─────────────────────────────────────────────────────────────────

function get_low_stock_items(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT p.name AS product,
               SUM(vld.qty_available) AS qty,
               p.alert_quantity,
               c.name AS category
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.alert_quantity IS NOT NULL
          AND vld.qty_available > 0
          AND vld.qty_available <= p.alert_quantity
        GROUP BY p.id
        ORDER BY qty ASC
        LIMIT 20
    ");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

// ── Monthly summary ───────────────────────────────────────────────────────────

function get_monthly_summary(int $months = 6): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
               COALESCE(SUM(final_total), 0)          AS revenue,
               COUNT(*)                                AS orders
        FROM transactions
        WHERE business_id = :bid AND type = 'sell' AND status = 'final'
          AND transaction_date >= DATE_SUB(NOW(), INTERVAL :months MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $st->bindValue(':bid',    $bid,    PDO::PARAM_INT);
    $st->bindValue(':months', $months, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
