<?php
// ── Category color coding (Boys = blue, Girls = pink, Unisex = orange) ────────
// Used everywhere a category name is charted or badged, so the same category
// always reads the same color across every page.

function category_color_key(string $name): string
{
    $n = strtolower($name);
    if (str_contains($n, 'boy'))    return 'blue';
    if (str_contains($n, 'girl'))   return 'pink';
    if (str_contains($n, 'unisex')) return 'orange';
    return 'slate';
}

function category_color_hex(string $name): string
{
    static $map = ['blue' => '#1d4ed8', 'pink' => '#db2777', 'orange' => '#f97316', 'slate' => '#64748b'];
    return $map[category_color_key($name)];
}

function category_chip_class(string $name): string
{
    return 'ck-chip-' . category_color_key($name);
}

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

// ── Business locations (for the Boys/Girls/Unisex stock filter) ───────────────

function get_business_locations(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("SELECT id, name FROM business_locations WHERE business_id = :bid AND is_active = 1 ORDER BY name");
    $st->execute([':bid' => $bid]);
    return $st->fetchAll();
}

// Top-level category ids that read as Boys/Girls/Unisex (same matching as category_color_key).
function get_core_category_ids(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("SELECT id, name FROM categories WHERE business_id = :bid AND parent_id = 0");
    $st->execute([':bid' => $bid]);

    $ids = [];
    foreach ($st->fetchAll() as $c) {
        if (category_color_key($c['name']) !== 'slate') $ids[] = (int)$c['id'];
    }
    return $ids;
}

// Current total units in stock for Boys/Girls/Unisex products, optionally at one location.
function get_core_current_stock(?int $location_id = null): float
{
    $db       = get_db();
    $bid      = business_id();
    $cat_ids  = get_core_category_ids();
    if (empty($cat_ids)) return 0.0;

    $placeholders = [];
    $params       = [':bid' => $bid];
    foreach ($cat_ids as $i => $id) {
        $key = ':c' . $i;
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $in = implode(',', $placeholders);

    $loc_sql = '';
    if ($location_id) {
        $loc_sql = ' AND vld.location_id = :loc';
        $params[':loc'] = $location_id;
    }

    $st = $db->prepare("
        SELECT COALESCE(SUM(vld.qty_available), 0)
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        WHERE p.category_id IN ($in) {$loc_sql}
    ");
    $st->execute($params);
    return (float)$st->fetchColumn();
}

// Daily received/sold/net for Boys/Girls/Unisex products only, optionally at one
// location, with the cumulative line anchored to the actual current stock
// quantity (at $to) instead of an arbitrary zero baseline.
function get_core_stock_movement(string $from, string $to, ?int $location_id = null): array
{
    $db      = get_db();
    $bid     = business_id();
    $cat_ids = get_core_category_ids();

    $current_stock = get_core_current_stock($location_id);

    $rows   = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);

    if (empty($cat_ids)) {
        while ($cursor <= $end) {
            $rows[] = ['day' => $cursor->format('Y-m-d'), 'received' => 0, 'sold' => 0, 'net' => 0, 'cumulative' => $current_stock];
            $cursor->modify('+1 day');
        }
        return $rows;
    }

    $placeholders = [];
    $params       = [':bid' => $bid, ':from' => $from, ':to' => $to];
    foreach ($cat_ids as $i => $id) {
        $key = ':c' . $i;
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $in = implode(',', $placeholders);

    $loc_sql = '';
    if ($location_id) {
        $loc_sql = ' AND t.location_id = :loc';
        $params[':loc'] = $location_id;
    }

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day, COALESCE(SUM(pl.quantity), 0) AS qty
        FROM purchase_lines pl
        JOIN transactions t ON t.id = pl.transaction_id
            AND t.type = 'purchase' AND t.status = 'received' AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to {$loc_sql}
        JOIN products p ON p.id = pl.product_id AND p.category_id IN ($in)
        GROUP BY day
    ");
    $st->execute($params);
    $recv = array_column($st->fetchAll(), 'qty', 'day');

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day, COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final' AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to {$loc_sql}
        JOIN products p ON p.id = sl.product_id AND p.category_id IN ($in)
        GROUP BY day
    ");
    $st->execute($params);
    $sold = array_column($st->fetchAll(), 'qty', 'day');

    // Build the raw (zero-based) daily series first...
    $cursor = new DateTime($from);
    $rel    = 0;
    $days   = [];
    while ($cursor <= $end) {
        $d     = $cursor->format('Y-m-d');
        $r     = (float)($recv[$d] ?? 0);
        $s     = (float)($sold[$d] ?? 0);
        $net   = $r - $s;
        $rel  += $net;
        $days[] = ['day' => $d, 'received' => $r, 'sold' => $s, 'net' => $net, 'relative' => $rel];
        $cursor->modify('+1 day');
    }

    // ...then shift every point so the series ends at the real current stock qty.
    $offset = $current_stock - (end($days)['relative'] ?? 0);
    foreach ($days as $day) {
        $rows[] = [
            'day'        => $day['day'],
            'received'   => $day['received'],
            'sold'       => $day['sold'],
            'net'        => $day['net'],
            'cumulative' => $day['relative'] + $offset,
        ];
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

// ── Stock by sub-category (size) ────────────────────────────────────────────────
// Aggregated by name across all parent categories, e.g. "Size 25-30" sums
// For Boys + For Girls + Unisex + Sneakers + CrabKids Sport, etc.

function get_stock_by_subcategory(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT sc.name AS sub_category,
               SUM(vld.qty_available) AS total_qty
        FROM variation_location_details vld
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        JOIN categories sc ON sc.id = p.sub_category_id
        WHERE vld.qty_available > 0
        GROUP BY sc.name
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
               MAX(pl_latest.purchase_price_inc_tax) AS cost_price,
               SUM(vld.qty_available * pl_latest.purchase_price_inc_tax) AS stock_value
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

// Shared category/sub-category WHERE fragment for the stock-level queries below.
function _category_filter_sql(array &$params, ?int $category_id, ?string $sub_category): string
{
    $where = [];
    if ($category_id) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $category_id;
    }
    if ($sub_category) {
        $where[] = 'sc.name = :sub_category';
        $params[':sub_category'] = $sub_category;
    }
    return $where ? (' AND ' . implode(' AND ', $where)) : '';
}

function get_low_stock_items(int $limit = 20, ?int $category_id = null, ?string $sub_category = null): array
{
    $db  = get_db();
    $bid = business_id();

    $params = [':bid' => $bid];
    $extra  = _category_filter_sql($params, $category_id, $sub_category);

    $st = $db->prepare("
        SELECT p.name AS product,
               v.name AS variation,
               SUM(vld.qty_available) AS qty,
               p.alert_quantity,
               c.name AS category
        FROM variation_location_details vld
        JOIN variations v ON v.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        LEFT JOIN categories c  ON c.id  = p.category_id
        LEFT JOIN categories sc ON sc.id = p.sub_category_id
        WHERE p.alert_quantity IS NOT NULL AND p.alert_quantity > 0 {$extra}
        GROUP BY v.id
        HAVING SUM(vld.qty_available) > 0 AND SUM(vld.qty_available) <= p.alert_quantity
        ORDER BY qty ASC
        LIMIT :lim
    ");
    foreach ($params as $key => $val) $st->bindValue($key, $val);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Out of stock ──────────────────────────────────────────────────────────────

function get_out_of_stock_items(int $limit = 200, ?int $category_id = null, ?string $sub_category = null): array
{
    $db  = get_db();
    $bid = business_id();

    $params = [':bid' => $bid];
    $extra  = _category_filter_sql($params, $category_id, $sub_category);

    $st = $db->prepare("
        SELECT p.name AS product,
               v.name AS variation,
               SUM(vld.qty_available) AS qty,
               c.name AS category
        FROM variation_location_details vld
        JOIN variations v ON v.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        LEFT JOIN categories c  ON c.id  = p.category_id
        LEFT JOIN categories sc ON sc.id = p.sub_category_id
        WHERE 1=1 {$extra}
        GROUP BY v.id
        HAVING SUM(vld.qty_available) <= 0
        ORDER BY p.name
        LIMIT :lim
    ");
    foreach ($params as $key => $val) $st->bindValue($key, $val);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Daily sold by size (variation name) ──────────────────────────────────────

function get_daily_sold_by_size(string $from, string $to): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day,
               pv.name AS size_name,
               COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty_sold
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        JOIN product_variations pv ON pv.id = sl.variation_id
        GROUP BY day, pv.name
        ORDER BY day, pv.name
    ");
    $st->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    $raw = $st->fetchAll();

    // Collect all sizes and all dates
    $sizes = array_values(array_unique(array_column($raw, 'size_name')));
    sort($sizes);

    // Build date spine
    $dates = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);
    while ($cursor <= $end) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    // Pivot: day → size → qty
    $pivot = [];
    foreach ($raw as $r) {
        $pivot[$r['day']][$r['size_name']] = (float)$r['qty_sold'];
    }

    return ['dates' => $dates, 'sizes' => $sizes, 'pivot' => $pivot];
}

// ── Daily sold by category ────────────────────────────────────────────────────

function get_daily_sold_by_category(string $from, string $to): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("
        SELECT DATE(t.transaction_date) AS day,
               COALESCE(c.name, 'Uncategorized') AS category,
               COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty_sold
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        JOIN product_variations pv ON pv.id = sl.variation_id
        JOIN products p ON p.id = pv.product_id AND p.business_id = :bid2 AND p.is_inactive = 0
        LEFT JOIN categories c ON c.id = p.category_id
        GROUP BY day, category
        ORDER BY day, category
    ");
    $st->execute([':bid' => $bid, ':bid2' => $bid, ':from' => $from, ':to' => $to]);
    $raw = $st->fetchAll();

    $categories = array_values(array_unique(array_column($raw, 'category')));
    sort($categories);

    $dates = [];
    $cursor = new DateTime($from);
    $end    = new DateTime($to);
    while ($cursor <= $end) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    $pivot = [];
    foreach ($raw as $r) {
        $pivot[$r['day']][$r['category']] = (float)$r['qty_sold'];
    }

    return ['dates' => $dates, 'categories' => $categories, 'pivot' => $pivot];
}

// ── Filter options: brands, categories, sub-categories ────────────────────────

function get_filter_options(): array
{
    $db  = get_db();
    $bid = business_id();

    $st = $db->prepare("SELECT id, name FROM brands WHERE business_id = :bid AND deleted_at IS NULL ORDER BY name");
    $st->execute([':bid' => $bid]);
    $brands = $st->fetchAll();

    $st = $db->prepare("SELECT id, name FROM categories WHERE business_id = :bid AND parent_id = 0 AND deleted_at IS NULL ORDER BY name");
    $st->execute([':bid' => $bid]);
    $categories = $st->fetchAll();

    $st = $db->prepare("SELECT DISTINCT name FROM categories WHERE business_id = :bid AND parent_id != 0 AND deleted_at IS NULL ORDER BY name");
    $st->execute([':bid' => $bid]);
    $sub_categories = array_column($st->fetchAll(), 'name');

    return compact('brands', 'categories', 'sub_categories');
}

// ── Best-selling products (filterable by brand / category / sub-category) ────

function get_best_selling_products(
    string $from,
    string $to,
    ?int $brand_id = null,
    ?int $category_id = null,
    ?string $sub_category = null,
    int $limit = 50
): array {
    $db  = get_db();
    $bid = business_id();

    $where  = [];
    $params = [':bid' => $bid, ':bid2' => $bid, ':from' => $from, ':to' => $to];

    if ($brand_id) {
        $where[] = 'p.brand_id = :brand_id';
        $params[':brand_id'] = $brand_id;
    }
    if ($category_id) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $category_id;
    }
    if ($sub_category) {
        $where[] = 'sc.name = :sub_category';
        $params[':sub_category'] = $sub_category;
    }
    $extra = $where ? (' AND ' . implode(' AND ', $where)) : '';

    $st = $db->prepare("
        SELECT p.id, p.name AS product,
               b.name  AS brand,
               c.name  AS category,
               sc.name AS sub_category,
               COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty_sold,
               COALESCE(SUM((sl.quantity - sl.quantity_returned) * sl.unit_price_inc_tax), 0) AS revenue
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        JOIN products p ON p.id = sl.product_id AND p.business_id = :bid2 AND p.is_inactive = 0
        LEFT JOIN brands     b  ON b.id  = p.brand_id
        LEFT JOIN categories c  ON c.id  = p.category_id
        LEFT JOIN categories sc ON sc.id = p.sub_category_id
        WHERE 1=1 {$extra}
        GROUP BY p.id
        ORDER BY qty_sold DESC
        LIMIT :lim
    ");
    foreach ($params as $key => $val) {
        $st->bindValue($key, $val);
    }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Restock requirement to reach a minimum stock level ─────────────────────────

function get_restock_requirements(float $min_stock = 4, ?int $category_id = null, ?string $sub_category = null): array
{
    $db  = get_db();
    $bid = business_id();

    $params = [':bid' => $bid];
    $extra  = _category_filter_sql($params, $category_id, $sub_category);

    $st = $db->prepare("
        SELECT p.name AS product,
               v.name AS variation,
               c.name AS category,
               SUM(vld.qty_available) AS qty,
               COALESCE(v.dpp_inc_tax, v.default_purchase_price, 0) AS purchase_price
        FROM variation_location_details vld
        JOIN variations v ON v.id = vld.variation_id
        JOIN products p ON p.id = vld.product_id AND p.business_id = :bid AND p.is_inactive = 0
        LEFT JOIN categories c  ON c.id  = p.category_id
        LEFT JOIN categories sc ON sc.id = p.sub_category_id
        WHERE 1=1 {$extra}
        GROUP BY v.id
        HAVING SUM(vld.qty_available) < :min_stock
        ORDER BY qty ASC
    ");
    foreach ($params as $key => $val) $st->bindValue($key, $val);
    $st->bindValue(':min_stock', $min_stock);
    $st->execute();
    $rows = $st->fetchAll();

    foreach ($rows as &$r) {
        $qty            = (float)$r['qty'];
        $r['shortfall'] = max(0, $min_stock - $qty);
        $r['restock_cost'] = $r['shortfall'] * (float)$r['purchase_price'];
    }
    unset($r);

    return $rows;
}

// ── Recent monthly sales trend (drives the income projection defaults) ────────
// Returns the last $months COMPLETE calendar months (oldest first), zero-filled
// for months with no sales, optionally scoped to a single brand/division.

function get_recent_sales_trend(int $months = 6, ?int $brand_id = null): array
{
    $db  = get_db();
    $bid = business_id();

    $end = new DateTime('first day of this month');
    $end->modify('-1 day'); // last day of the previous (most recent complete) month
    $start = new DateTime($end->format('Y-m-01'));
    $start->modify('-' . ($months - 1) . ' months');

    $from = $start->format('Y-m-d');
    $to   = $end->format('Y-m-d');

    if ($brand_id) {
        $st = $db->prepare("
            SELECT DATE_FORMAT(t.transaction_date, '%Y-%m') AS ym,
                   COALESCE(SUM((sl.quantity - sl.quantity_returned) * sl.unit_price_inc_tax), 0) AS revenue
            FROM transaction_sell_lines sl
            JOIN transactions t ON t.id = sl.transaction_id
                AND t.type = 'sell' AND t.status = 'final'
                AND t.business_id = :bid
                AND DATE(t.transaction_date) BETWEEN :from AND :to
            JOIN products p ON p.id = sl.product_id AND p.business_id = :bid2 AND p.brand_id = :brand_id
            GROUP BY ym
        ");
        $st->execute([':bid' => $bid, ':bid2' => $bid, ':brand_id' => $brand_id, ':from' => $from, ':to' => $to]);
    } else {
        $st = $db->prepare("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym,
                   COALESCE(SUM(final_total), 0) AS revenue
            FROM transactions
            WHERE business_id = :bid AND type = 'sell' AND status = 'final'
              AND DATE(transaction_date) BETWEEN :from AND :to
            GROUP BY ym
        ");
        $st->execute([':bid' => $bid, ':from' => $from, ':to' => $to]);
    }
    $map = array_column($st->fetchAll(), 'revenue', 'ym');

    $rows   = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $key    = $cursor->format('Y-m');
        $rows[] = ['month' => $key, 'label' => $cursor->format('M Y'), 'revenue' => (float)($map[$key] ?? 0)];
        $cursor->modify('+1 month');
    }
    return $rows;
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

// ── Targets Monitoring: purchase amount vs. M-Pesa balance ────────────────────

function ensure_purchase_targets_table(): void
{
    $db = get_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_targets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            business_id INT UNSIGNED NOT NULL,
            purchase_amount DECIMAL(15,2) NOT NULL,
            mpesa_balance DECIMAL(15,2) NOT NULL,
            bank_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
            deficit DECIMAL(15,2) NOT NULL,
            deficit_pct DECIMAL(8,2) NOT NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY business_id (business_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Self-heal: add bank_balance for installs created before this column existed.
    $has_col = $db->query("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'purchase_targets' AND column_name = 'bank_balance'
    ")->fetchColumn();
    if (!$has_col) {
        $db->exec("ALTER TABLE purchase_targets ADD COLUMN bank_balance DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER mpesa_balance");
    }
}

function save_purchase_target(float $purchase_amount, float $mpesa_balance, float $bank_balance = 0, ?string $notes = null): array
{
    $db  = get_db();
    $bid = business_id();
    ensure_purchase_targets_table();

    $available   = $mpesa_balance + $bank_balance;
    $deficit     = $purchase_amount - $available;
    $deficit_pct = $purchase_amount > 0 ? ($deficit / $purchase_amount * 100) : 0;

    $st = $db->prepare("
        INSERT INTO purchase_targets (business_id, purchase_amount, mpesa_balance, bank_balance, deficit, deficit_pct, notes)
        VALUES (:bid, :purchase_amount, :mpesa_balance, :bank_balance, :deficit, :deficit_pct, :notes)
    ");
    $st->execute([
        ':bid'             => $bid,
        ':purchase_amount' => $purchase_amount,
        ':mpesa_balance'   => $mpesa_balance,
        ':bank_balance'    => $bank_balance,
        ':deficit'         => $deficit,
        ':deficit_pct'     => $deficit_pct,
        ':notes'           => $notes,
    ]);

    return ['deficit' => $deficit, 'deficit_pct' => $deficit_pct];
}

function get_purchase_targets(int $limit = 50): array
{
    $db  = get_db();
    $bid = business_id();
    ensure_purchase_targets_table();

    $st = $db->prepare("
        SELECT id, purchase_amount, mpesa_balance, bank_balance, deficit, deficit_pct, notes, created_at
        FROM purchase_targets
        WHERE business_id = :bid
        ORDER BY created_at DESC
        LIMIT :lim
    ");
    $st->bindValue(':bid', $bid, PDO::PARAM_INT);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function delete_purchase_target(int $id): void
{
    $db  = get_db();
    $bid = business_id();
    ensure_purchase_targets_table();

    $st = $db->prepare("DELETE FROM purchase_targets WHERE id = :id AND business_id = :bid");
    $st->execute([':id' => $id, ':bid' => $bid]);
}

function get_suggested_purchase_amount(): float
{
    $rows = get_restock_requirements(4);
    return array_sum(array_column($rows, 'restock_cost'));
}

// ── Latest M-Pesa balance (from the Daraja callback log, not business-scoped) ──

function get_latest_mpesa_balance(): ?array
{
    $db = get_db();

    $st = $db->query("
        SELECT org_account_balance AS balance, created_at
        FROM mpesa_transactions
        WHERE org_account_balance IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $row = $st->fetch();
    return $row ?: null;
}
