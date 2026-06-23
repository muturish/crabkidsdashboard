<?php
// ── KPI helpers ───────────────────────────────────────────────────────────────

function get_total_stock_value(): float
{
    $db  = get_db();
    $bid = business_id();
    $sql = "
        SELECT COALESCE(SUM(vld.qty_available * pp.dpp_inc_tax), 0) AS val
        FROM variation_location_details vld
        JOIN product_variations pv ON pv.id = vld.product_variation_id
        JOIN purchase_lines pp ON pp.variation_id = pv.id
        JOIN transactions t ON t.id = pp.transaction_id
            AND t.type = 'purchase' AND t.status = 'received' AND t.business_id = :bid
        WHERE vld.qty_available > 0
    ";
    return (float) $db->prepare($sql) && ($st = $db->prepare($sql)) && $st->execute([':bid' => $bid])
        ? $st->fetchColumn()
        : 0.0;
}

function get_kpis(): array
{
    $db  = get_db();
    $bid = business_id();

    // Total products
    $st = $db->prepare("SELECT COUNT(*) FROM products WHERE business_id = :bid AND deleted_at IS NULL");
    $st->execute([':bid' => $bid]);
    $total_products = (int) $st->fetchColumn();

    // Total variations in stock
    $st = $db->prepare("
        SELECT COUNT(DISTINCT pv.id)
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         WHERE vld.qty_available > 0
    ");
    $st->execute([':bid' => $bid]);
    $in_stock = (int) $st->fetchColumn();

    // Low stock count
    $st = $db->prepare("
        SELECT COUNT(DISTINCT pv.id)
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         WHERE vld.qty_available > 0 AND vld.qty_available <= p.alert_quantity
    ");
    $st->execute([':bid' => $bid]);
    $low_stock = (int) $st->fetchColumn();

    // Out of stock
    $st = $db->prepare("
        SELECT COUNT(DISTINCT pv.id)
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         WHERE vld.qty_available <= 0
    ");
    $st->execute([':bid' => $bid]);
    $out_of_stock = (int) $st->fetchColumn();

    // Total stock value
    $st = $db->prepare("
        SELECT COALESCE(SUM(vld.qty_available * pv.default_sell_price), 0)
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         WHERE vld.qty_available > 0
    ");
    $st->execute([':bid' => $bid]);
    $stock_value = (float) $st->fetchColumn();

    return compact('total_products', 'in_stock', 'low_stock', 'out_of_stock', 'stock_value');
}

// ── Stock by category ─────────────────────────────────────────────────────────

function get_stock_by_category(): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("
        SELECT c.name AS category,
               SUM(vld.qty_available) AS total_qty
        FROM variation_location_details vld
        JOIN product_variations pv ON pv.id = vld.product_variation_id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         JOIN categories c ON c.id = p.category_id
        WHERE vld.qty_available > 0
        GROUP BY c.id, c.name
        ORDER BY total_qty DESC
    ");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

// ── Stock growth trend (daily) ────────────────────────────────────────────────

function get_stock_growth(string $from, string $to): array
{
    $db  = get_db();
    $bid = business_id();

    $received = $db->prepare("
        SELECT DATE(t.transaction_date) AS day, SUM(pl.quantity) AS qty
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
            AND t.type = 'purchase' AND t.status = 'received'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        GROUP BY day ORDER BY day
    ");
    $received->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    $recv_map = array_column($received->fetchAll(), 'qty', 'day');

    $sold = $db->prepare("
        SELECT DATE(t.transaction_date) AS day,
               SUM(sl.quantity - COALESCE(sl.quantity_returned, 0)) AS qty
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        GROUP BY day ORDER BY day
    ");
    $sold->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    $sold_map = array_column($sold->fetchAll(), 'qty', 'day');

    // Build a day-by-day series
    $days   = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);
    while ($cursor <= $end) {
        $days[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    $rows       = [];
    $cumulative = 0;
    foreach ($days as $day) {
        $r           = (float)($recv_map[$day] ?? 0);
        $s           = (float)($sold_map[$day] ?? 0);
        $net         = $r - $s;
        $cumulative += $net;
        $rows[]      = ['day' => $day, 'received' => $r, 'sold' => $s, 'net' => $net, 'cumulative' => $cumulative];
    }
    return $rows;
}

// ── Most restocked products ───────────────────────────────────────────────────

function get_top_restocked(string $from, string $to, int $limit = 10): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("
        SELECT p.name AS product,
               COALESCE(pv.name, 'Default') AS variation,
               SUM(pl.quantity) AS total_received
        FROM purchase_lines pl
        JOIN product_variations pv ON pv.id = pl.variation_id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         JOIN transactions t ON t.id = pl.transaction_id
            AND t.type = 'purchase' AND t.status = 'received'
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        GROUP BY pv.id
        ORDER BY total_received DESC
        LIMIT :lim
    ");
    $st->bindValue(':bid',  $bid,   PDO::PARAM_INT);
    $st->bindValue(':from', $from,  PDO::PARAM_STR);
    $st->bindValue(':to',   $to,    PDO::PARAM_STR);
    $st->bindValue(':lim',  $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Low-stock & out-of-stock ──────────────────────────────────────────────────

function get_low_stock_items(): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("
        SELECT p.name AS product,
               COALESCE(pv.name, 'Default') AS variation,
               SUM(vld.qty_available) AS qty,
               p.alert_quantity,
               c.name AS category
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         LEFT JOIN categories c ON c.id = p.category_id
        WHERE vld.qty_available > 0 AND vld.qty_available <= p.alert_quantity
        GROUP BY pv.id
        ORDER BY qty ASC
    ");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

function get_out_of_stock_items(): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("
        SELECT p.name AS product,
               COALESCE(pv.name, 'Default') AS variation,
               c.name AS category
        FROM product_variations pv
        JOIN variation_location_details vld ON vld.product_variation_id = pv.id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid         LEFT JOIN categories c ON c.id = p.category_id
        WHERE vld.qty_available <= 0
        GROUP BY pv.id
        ORDER BY p.name
    ");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

// ── Recent sales ──────────────────────────────────────────────────────────────

function get_recent_sales(int $limit = 8): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("
        SELECT t.invoice_no,
               DATE(t.transaction_date) AS sale_date,
               t.final_total,
               COUNT(sl.id) AS items
        FROM transactions t
        JOIN transaction_sell_lines sl ON sl.transaction_id = t.id
        WHERE t.type = 'sell' AND t.status = 'final' AND t.business_id = :bid
        GROUP BY t.id
        ORDER BY t.transaction_date DESC
        LIMIT :lim
    ");
    $st->bindValue(':bid', $bid,   PDO::PARAM_INT);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Locations ─────────────────────────────────────────────────────────────────

function get_locations(): array
{
    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("SELECT id, name FROM business_locations WHERE business_id = :bid ORDER BY name");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}
