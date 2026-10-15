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
        GROUP BY v.id, p.id, c.id
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
        GROUP BY v.id, p.id, c.id
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
               v.name AS size_name,
               COALESCE(SUM(sl.quantity - sl.quantity_returned), 0) AS qty_sold
        FROM transaction_sell_lines sl
        JOIN transactions t ON t.id = sl.transaction_id
            AND t.type = 'sell' AND t.status = 'final'
            AND t.business_id = :bid
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        JOIN variations v ON v.id = sl.variation_id
        GROUP BY day, v.name
        ORDER BY day, v.name
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
        JOIN variations v ON v.id = sl.variation_id
        JOIN products p ON p.id = v.product_id AND p.business_id = :bid2 AND p.is_inactive = 0
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
        GROUP BY p.id, b.id, c.id, sc.id
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
        GROUP BY v.id, p.id, c.id
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

// Search successful M-Pesa transactions (Daraja callback log) so a call-center
// order can be matched to the real payment -- by receipt code, phone, or the
// payer's name as M-Pesa reports it. Only 'success' rows are real payments;
// 'failed'/'pending' rows are just callback noise, not money received.
// $q empty returns the most recent successful transactions (for browsing);
// non-empty filters by receipt code, phone, or payer name.
function search_mpesa_transactions(string $q = '', int $limit = 10): array
{
    $db = get_db();
    $q  = trim($q);
    ensure_call_center_orders_table();

    // Only receipts not yet used on a POS sale (transaction_payments.transaction_no
    // is where Ultimate POS stores the M-Pesa code of each payment). With no
    // search term the browse list shows just the past 3 days; a search
    // covers all dates. Receipts already attached to a call center order are
    // still listed, flagged with used_order_id/used_order_no, so the UI can
    // say where they were used instead of letting them be picked again.
    $where  = [
        "m.status = 'success'",
        "NOT EXISTS (SELECT 1 FROM transaction_payments tp WHERE tp.transaction_no = m.receipt_number)",
    ];
    $params = [':bid' => business_id()];
    if ($q !== '') {
        $where[] = '(m.receipt_number LIKE :q OR m.mpesa_number LIKE :q OR m.first_name LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    } else {
        $where[] = 'm.created_at >= NOW() - INTERVAL 3 DAY';
    }

    $st = $db->prepare("
        SELECT m.id, m.receipt_number, m.mpesa_number, m.first_name, m.amount, m.created_at,
               cco.id AS used_order_id, cco.order_no AS used_order_no
        FROM mpesa_transactions m
        LEFT JOIN call_centre_orders cco
               ON cco.mpesa_receipt_number = m.receipt_number COLLATE utf8mb4_general_ci
              AND cco.business_id = :bid
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.created_at DESC
        LIMIT :lim
    ");
    foreach ($params as $k => $v) { $st->bindValue($k, $v); }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// Confirms a receipt number picked in the UI is a real, successful M-Pesa
// payment -- never trust the client's word for what it "matched".
function find_mpesa_transaction(string $receipt_number): ?array
{
    $db = get_db();
    $st = $db->prepare("SELECT id, receipt_number, mpesa_number, first_name, amount, created_at FROM mpesa_transactions WHERE status = 'success' AND receipt_number = :r LIMIT 1");
    $st->execute([':r' => $receipt_number]);
    return $st->fetch() ?: null;
}

// True if the M-Pesa code has already been recorded against a POS sale payment.
function mpesa_receipt_used_in_pos(string $receipt_number): bool
{
    $st = get_db()->prepare("SELECT 1 FROM transaction_payments WHERE transaction_no = :r LIMIT 1");
    $st->execute([':r' => $receipt_number]);
    return (bool)$st->fetchColumn();
}

// The call center order (id, order_no) an M-Pesa code is already attached to, if any.
function find_call_center_order_by_mpesa(string $receipt_number): ?array
{
    ensure_call_center_orders_table();
    $st = get_db()->prepare("SELECT id, order_no FROM call_centre_orders WHERE mpesa_receipt_number = :r AND business_id = :bid LIMIT 1");
    $st->execute([':r' => $receipt_number, ':bid' => business_id()]);
    return $st->fetch() ?: null;
}

// ── Transactions report (sales, payment method, M-Pesa reconciliation) ────────

// Reads the business's own configured payment method labels (e.g. custom_pay_1
// renamed to "Mpesa Online") so the report always matches what's on the POS.
function get_payment_method_labels(): array
{
    $defaults = [
        'cash'          => 'Cash',
        'card'          => 'Card',
        'cheque'        => 'Cheque',
        'bank_transfer' => 'Bank Transfer',
        'other'         => 'Other',
        'custom_pay_1'  => 'Custom Payment 1',
        'custom_pay_2'  => 'Custom Payment 2',
        'custom_pay_3'  => 'Custom Payment 3',
        'custom_pay_4'  => 'Custom Payment 4',
        'custom_pay_5'  => 'Custom Payment 5',
        'custom_pay_6'  => 'Custom Payment 6',
        'custom_pay_7'  => 'Custom Payment 7',
    ];

    $db  = get_db();
    $bid = business_id();
    $st  = $db->prepare("SELECT common_settings FROM business WHERE id = :bid");
    $st->execute([':bid' => $bid]);
    $json = $st->fetchColumn();

    if ($json) {
        $settings = json_decode($json, true);
        if (!empty($settings['payments']) && is_array($settings['payments'])) {
            foreach ($settings['payments'] as $key => $label) {
                if (!empty($label)) $defaults[$key] = $label;
            }
        }
    }
    return $defaults;
}

// Payment method choices for call-center orders. M-Pesa is the only payment
// method this business accepts for phone/WhatsApp orders, regardless of
// delivery mode -- so it's the only option, not a POS-configured list.
function get_call_center_payment_methods(): array
{
    return ['mpesa' => 'M-Pesa'];
}

// One row per payment (not per sale), so split payments show each method and,
// for any leg that matches an M-Pesa receipt, that payment's real M-Pesa date.
function get_sales_transactions(string $from, string $to, ?string $method = null, int $limit = 500): array
{
    $db  = get_db();
    $bid = business_id();

    $method_sql = $method ? ' AND tp.method = :method' : '';

    $st = $db->prepare("
        SELECT t.invoice_no,
               t.transaction_date AS sale_date,
               t.final_total      AS sale_total,
               c.name             AS customer,
               tp.method,
               tp.amount,
               tp.transaction_no,
               tp.payment_ref_no,
               mt.created_at      AS mpesa_date,
               mt.mpesa_number,
               mt.first_name      AS mpesa_payer
        FROM transaction_payments tp
        JOIN transactions t ON t.id = tp.transaction_id
            AND t.business_id = :bid AND t.type = 'sell' AND t.status = 'final'
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        LEFT JOIN contacts c ON c.id = t.contact_id
        LEFT JOIN mpesa_transactions mt ON mt.receipt_number = tp.transaction_no
        WHERE tp.is_return = 0 {$method_sql}
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT :lim
    ");
    $st->bindValue(':bid', $bid, PDO::PARAM_INT);
    $st->bindValue(':from', $from);
    $st->bindValue(':to', $to);
    if ($method) $st->bindValue(':method', $method);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// Aggregate totals for the whole filtered range (not capped like the row
// list above), so the KPI cards stay correct even when there are more than
// $limit payments in range. M-Pesa is identified by a matched receipt, same
// evidence-based rule as the row list -- not by assuming which custom_pay_N
// is M-Pesa.
function get_sales_payment_summary(string $from, string $to, ?string $method = null, ?string $prepaid_key = null): array
{
    $db  = get_db();
    $bid = business_id();

    $method_sql = $method ? ' AND tp.method = :method' : '';

    $st = $db->prepare("
        SELECT
            COALESCE(SUM(tp.amount), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN tp.method = 'cash' THEN tp.amount ELSE 0 END), 0) AS cash_amount,
            COALESCE(SUM(CASE WHEN mt.receipt_number IS NOT NULL THEN tp.amount ELSE 0 END), 0) AS mpesa_amount,
            COALESCE(SUM(CASE WHEN tp.method = :prepaid_key THEN tp.amount ELSE 0 END), 0) AS prepaid_amount,
            COUNT(*) AS payment_count,
            SUM(CASE WHEN mt.receipt_number IS NOT NULL THEN 1 ELSE 0 END) AS mpesa_count
        FROM transaction_payments tp
        JOIN transactions t ON t.id = tp.transaction_id
            AND t.business_id = :bid AND t.type = 'sell' AND t.status = 'final'
            AND DATE(t.transaction_date) BETWEEN :from AND :to
        LEFT JOIN mpesa_transactions mt ON mt.receipt_number = tp.transaction_no
        WHERE tp.is_return = 0 {$method_sql}
    ");
    $st->bindValue(':bid', $bid, PDO::PARAM_INT);
    $st->bindValue(':from', $from);
    $st->bindValue(':to', $to);
    $st->bindValue(':prepaid_key', $prepaid_key ?? '__none__');
    if ($method) $st->bindValue(':method', $method);
    $st->execute();
    return $st->fetch();
}

// ── Call Center Orders (call_centre_orders table in the POS database) ────────
//
// Stored in `call_centre_orders`, a table that already exists in the POS
// database (customer_name, customer_phone, delivery_address,
// mode_of_delivery, mode_of_payment, notes). The dashboard extends it with
// the extra columns it needs and keeps line items in call_centre_order_items.
// The original `item` text column is dropped -- products live only in
// call_centre_order_items.
//
// These orders never touch Ultimate POS's own transactions/stock tables --
// its FIFO purchase-allocation logic is too fragile to write into directly
// (see this project's long history of "mismatch" stock-integrity fixes).
// A call-center order is a holding record; a staff member later re-enters
// it as a real sale inside Ultimate POS itself. Once placed, an order is
// locked -- only its notes can be edited.
//
// The pages use the dashboard's own field names; CC_ORDER_SELECT maps the
// table's columns onto them.

const CC_ORDER_SELECT = "o.*, o.delivery_address AS customer_address,
    o.mode_of_delivery AS delivery_method, o.mode_of_payment AS payment_method";

function ensure_call_center_orders_table(): void
{
    static $done = false;
    if ($done) return;
    $db = get_db();

    // Base table as it exists on the live POS database -- only created here
    // for installs that don't have it yet.
    $db->exec("
        CREATE TABLE IF NOT EXISTS call_centre_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(30) NOT NULL,
            delivery_address TEXT NULL,
            mode_of_delivery VARCHAR(100) NULL,
            mode_of_payment VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Columns the dashboard adds on top of the base table.
    $extra = [
        'business_id'          => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER id",
        'order_no'             => "VARCHAR(20) NULL AFTER business_id",
        'payment_status'       => "ENUM('paid','pod') NOT NULL DEFAULT 'pod' AFTER mode_of_payment",
        'mpesa_receipt_number' => "VARCHAR(50) NULL AFTER payment_status",
        'order_status'         => "ENUM('pending','converted','cancelled') NOT NULL DEFAULT 'pending' AFTER mpesa_receipt_number",
        'subtotal_amount'      => "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER order_status",
        'discount_amount'      => "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER subtotal_amount",
        'shipping_amount'      => "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER discount_amount",
        'total_amount'         => "DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER shipping_amount",
        'agent_name'           => "VARCHAR(100) NULL AFTER total_amount",
        'notes_updated_at'     => "TIMESTAMP NULL DEFAULT NULL AFTER notes",
    ];
    $cols = $db->query("
        SELECT column_name FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'call_centre_orders'
    ")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($extra as $col => $def) {
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE call_centre_orders ADD COLUMN $col $def");
        }
    }
    // The original table's `item` column (NOT NULL, no default) is no longer
    // used -- drop it, or inserts that leave it out would fail.
    if (in_array('item', $cols, true)) {
        $db->exec("ALTER TABLE call_centre_orders DROP COLUMN item");
    }
    $keys = $db->query("SHOW INDEX FROM call_centre_orders")->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('order_no', $keys, true))     $db->exec("ALTER TABLE call_centre_orders ADD UNIQUE KEY order_no (order_no)");
    if (!in_array('business_id', $keys, true))  $db->exec("ALTER TABLE call_centre_orders ADD KEY business_id (business_id)");
    if (!in_array('order_status', $keys, true)) $db->exec("ALTER TABLE call_centre_orders ADD KEY order_status (order_status)");
    if (!in_array('mpesa_receipt_number', $keys, true)) {
        // One M-Pesa code per order, enforced by the database so two agents
        // saving at the same moment can't both use it. Skipped (and retried
        // next load) if existing rows already share a code.
        try {
            $db->exec("ALTER TABLE call_centre_orders ADD UNIQUE KEY mpesa_receipt_number (mpesa_receipt_number)");
        } catch (PDOException $e) {}
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS call_centre_order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            variation_id INT UNSIGNED NULL,
            product_name VARCHAR(191) NOT NULL,
            variation_name VARCHAR(191) NULL,
            sku VARCHAR(100) NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            KEY order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $done = true;
}

// Product/variation grid for the call-center order builder (POS-style product
// picker). Only the `variations` table holds real size/value names -- never
// product_variations, which is just the variation *template* (see the Sales
// by Size fix history). Only shows items with stock on hand somewhere.
function get_pos_products(?string $q = null, ?int $category_id = null, ?int $brand_id = null, int $limit = 60): array
{
    $db  = get_db();
    $bid = business_id();
    $q   = trim((string)$q);

    $where  = ['v.deleted_at IS NULL'];
    $params = [':bid' => $bid];

    if ($q !== '') {
        $where[] = '(p.name LIKE :q OR v.name LIKE :q OR v.sub_sku LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($category_id) {
        $where[] = 'p.category_id = :cat';
        $params[':cat'] = $category_id;
    }
    if ($brand_id) {
        $where[] = 'p.brand_id = :brand';
        $params[':brand'] = $brand_id;
    }

    $st = $db->prepare("
        SELECT v.id AS variation_id, p.name AS product_name, v.name AS variation_name,
               v.sub_sku AS sku, COALESCE(v.default_sell_price, 0) AS price,
               COALESCE(SUM(vld.qty_available), 0) AS stock
        FROM variations v
        JOIN products p ON p.id = v.product_id AND p.business_id = :bid AND p.is_inactive = 0
        LEFT JOIN variation_location_details vld ON vld.variation_id = v.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY v.id, p.name, v.name, v.sub_sku, v.default_sell_price
        HAVING stock > 0
        ORDER BY p.name, v.name
        LIMIT :lim
    ");
    foreach ($params as $k => $v) { $st->bindValue($k, $v); }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// $items: list of ['variation_id'=>?int, 'product_name'=>string, 'variation_name'=>?string,
//                   'sku'=>?string, 'quantity'=>int, 'unit_price'=>float]
function create_call_center_order(array $order, array $items): array
{
    $db  = get_db();
    $bid = business_id();
    ensure_call_center_orders_table();

    if (empty($items)) {
        throw new Exception('Order must have at least one item.');
    }

    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float)$it['unit_price'] * (int)$it['quantity'];
    }
    $discount = max(0, (float)($order['discount_amount'] ?? 0));
    $shipping = max(0, (float)($order['shipping_amount'] ?? 0));
    $total    = max(0, $subtotal - $discount + $shipping);

    $db->beginTransaction();
    try {
        $st = $db->prepare("
            INSERT INTO call_centre_orders
                (business_id, order_no, customer_name, customer_phone, delivery_address,
                 mode_of_delivery, payment_status, mode_of_payment, mpesa_receipt_number, order_status,
                 subtotal_amount, discount_amount, shipping_amount, total_amount, agent_name, notes)
            VALUES (:bid, NULL, :name, :phone, :address, :dm, :ps, :pm, :mpesa, 'pending', :subtotal, :discount,
                 :shipping, :total, :agent, :notes)
        ");
        $st->execute([
            ':bid'      => $bid,
            ':name'     => $order['customer_name'],
            ':phone'    => $order['customer_phone'],
            ':address'  => $order['customer_address'] ?: null,
            ':dm'       => $order['delivery_method'],
            ':ps'       => $order['payment_status'],
            ':pm'       => $order['payment_status'] === 'paid' ? ($order['payment_method'] ?? null) : null,
            ':mpesa'    => $order['payment_method'] === 'mpesa' ? ($order['mpesa_receipt_number'] ?? null) : null,
            ':subtotal' => $subtotal,
            ':discount' => $discount,
            ':shipping' => $shipping,
            ':total'    => $total,
            ':agent'   => $order['agent_name'] ?: null,
            ':notes'   => $order['notes'] ?: null,
        ]);
        $id = (int)$db->lastInsertId();
        $order_no = 'CC-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE call_centre_orders SET order_no = :no WHERE id = :id")
           ->execute([':no' => $order_no, ':id' => $id]);

        $ist = $db->prepare("
            INSERT INTO call_centre_order_items
                (order_id, variation_id, product_name, variation_name, sku, quantity, unit_price, line_total)
            VALUES (:oid, :vid, :pname, :vname, :sku, :qty, :price, :ltotal)
        ");
        foreach ($items as $it) {
            $qty   = max(1, (int)$it['quantity']);
            $price = max(0, (float)$it['unit_price']);
            $ist->execute([
                ':oid'    => $id,
                ':vid'    => $it['variation_id'] ?: null,
                ':pname'  => $it['product_name'],
                ':vname'  => $it['variation_name'] ?: null,
                ':sku'    => $it['sku'] ?: null,
                ':qty'    => $qty,
                ':price'  => $price,
                ':ltotal' => $qty * $price,
            ]);
        }

        $db->commit();
        return ['id' => $id, 'order_no' => $order_no, 'total' => $total];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_call_center_orders(array $filters = [], int $limit = 200): array
{
    $db  = get_db();
    $bid = business_id();
    ensure_call_center_orders_table();

    $where  = ['o.business_id = :bid'];
    $params = [':bid' => $bid];

    if (!empty($filters['from'])) { $where[] = 'DATE(o.created_at) >= :from'; $params[':from'] = $filters['from']; }
    if (!empty($filters['to']))   { $where[] = 'DATE(o.created_at) <= :to';   $params[':to']   = $filters['to']; }
    if (!empty($filters['order_status']))    { $where[] = 'o.order_status = :os';     $params[':os'] = $filters['order_status']; }
    if (!empty($filters['payment_status']))  { $where[] = 'o.payment_status = :ps';   $params[':ps'] = $filters['payment_status']; }
    if (!empty($filters['delivery_method'])) { $where[] = 'o.mode_of_delivery = :dm'; $params[':dm'] = $filters['delivery_method']; }
    if (!empty($filters['search'])) {
        $where[] = '(o.order_no LIKE :s OR o.customer_name LIKE :s OR o.customer_phone LIKE :s)';
        $params[':s'] = '%' . $filters['search'] . '%';
    }

    $sql = "SELECT " . CC_ORDER_SELECT . " FROM call_centre_orders o WHERE " . implode(' AND ', $where) . " ORDER BY o.created_at DESC LIMIT :lim";
    $st  = $db->prepare($sql);
    foreach ($params as $k => $v) { $st->bindValue($k, $v); }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    if ($rows) {
        $ids  = array_column($rows, 'id');
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $cst  = $db->prepare("SELECT order_id, COUNT(*) AS n FROM call_centre_order_items WHERE order_id IN ($in) GROUP BY order_id");
        $cst->execute($ids);
        $counts = array_column($cst->fetchAll(), 'n', 'order_id');
        foreach ($rows as &$r) { $r['item_count'] = $counts[$r['id']] ?? 0; }
        unset($r);
    }

    return $rows;
}

function get_call_center_order_stats(?string $from = null, ?string $to = null): array
{
    $db  = get_db();
    $bid = business_id();
    ensure_call_center_orders_table();

    $where  = ['business_id = :bid'];
    $params = [':bid' => $bid];
    if ($from) { $where[] = 'DATE(created_at) >= :from'; $params[':from'] = $from; }
    if ($to)   { $where[] = 'DATE(created_at) <= :to';   $params[':to']   = $to; }

    $st = $db->prepare("
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_value,
            COALESCE(SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN payment_status = 'pod' AND order_status != 'cancelled' THEN 1 ELSE 0 END), 0) AS pod_count,
            COALESCE(SUM(CASE WHEN payment_status = 'pod' AND order_status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS pod_value
        FROM call_centre_orders
        WHERE " . implode(' AND ', $where) . "
    ");
    $st->execute($params);
    return $st->fetch() ?: ['total_orders' => 0, 'total_value' => 0, 'pending_count' => 0, 'pod_count' => 0, 'pod_value' => 0];
}

function get_call_center_order(int $id): ?array
{
    $db  = get_db();
    $bid = business_id();
    ensure_call_center_orders_table();

    $st = $db->prepare("SELECT " . CC_ORDER_SELECT . " FROM call_centre_orders o WHERE o.id = :id AND o.business_id = :bid");
    $st->execute([':id' => $id, ':bid' => $bid]);
    $order = $st->fetch();
    if (!$order) return null;

    $ist = $db->prepare("SELECT * FROM call_centre_order_items WHERE order_id = :id ORDER BY id");
    $ist->execute([':id' => $id]);
    $order['items'] = $ist->fetchAll();

    return $order;
}

// Orders are locked once placed -- notes are the only field that can change.
function update_call_center_order_notes(int $id, string $notes): void
{
    $db  = get_db();
    $bid = business_id();
    ensure_call_center_orders_table();

    // notes_updated_at is stamped only when the text actually changes (MySQL
    // evaluates SET left to right, so the comparison sees the old notes).
    $db->prepare("
        UPDATE call_centre_orders
        SET notes_updated_at = IF(notes <=> :cmp, notes_updated_at, NOW()),
            notes = :notes
        WHERE id = :id AND business_id = :bid
    ")->execute([':cmp' => $notes !== '' ? $notes : null, ':notes' => $notes !== '' ? $notes : null, ':id' => $id, ':bid' => $bid]);
}
