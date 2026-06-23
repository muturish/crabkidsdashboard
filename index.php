<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Overview';
$page_subtitle = 'Live stock & sales snapshot — ' . date('l, d F Y');

// Date range (default: last 30 days)
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

try {
    $kpis      = get_kpis();
    $movement  = get_daily_stock_movement($from, $to);
    $revenue   = get_daily_revenue($from, $to);
    $categories = get_stock_by_category();
    $top_value = get_top_stock_value(10);
    $low_stock = get_low_stock_items();
    $monthly   = get_monthly_summary(6);
    $db_error  = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $kpis = ['total_products'=>0,'total_units'=>0,'stock_cost_value'=>0,'stock_sell_value'=>0,'low_stock'=>0,'out_of_stock'=>0,'today_sales'=>0];
    $movement = $revenue = $categories = $top_value = $low_stock = $monthly = [];
}

// Chart data
$days_labels  = json_encode(array_column($movement, 'day'));
$recv_data    = json_encode(array_column($movement, 'received'));
$sold_data    = json_encode(array_column($movement, 'sold'));
$cumul_data   = json_encode(array_column($movement, 'cumulative'));
$rev_labels   = json_encode(array_column($revenue,  'day'));
$rev_data     = json_encode(array_column($revenue,  'revenue'));
$cat_labels   = json_encode(array_column($categories, 'category'));
$cat_values   = json_encode(array_column($categories, 'total_qty'));
$mon_labels   = json_encode(array_column($monthly, 'month'));
$mon_revenue  = json_encode(array_column($monthly, 'revenue'));

$total_recv = array_sum(array_column($movement, 'received'));
$total_sold = array_sum(array_column($movement, 'sold'));
$net_change = !empty($movement) ? end($movement)['cumulative'] : 0;
$total_rev  = array_sum(array_column($revenue, 'revenue'));

$inline_scripts = <<<JS
const palette = ['#0d3b8e','#f97316','#10b981','#1a56c4','#ea6c0a','#0891b2','#7c3aed','#ec4899','#f59e0b','#14b8a6'];

// ── Tick helper ─────────────────────────────────────────────
function maxTicks(labels, n) {
    return labels.length > n ? { maxTicksLimit: n } : {};
}

// ── 1. Stock movement bar chart ──────────────────────────────
const movCtx = document.getElementById('movChart');
if (movCtx) {
    new Chart(movCtx, {
        type: 'bar',
        data: {
            labels: {$days_labels},
            datasets: [
                { label: 'Received', data: {$recv_data}, backgroundColor: 'rgba(13,59,142,.8)', borderRadius: 3 },
                { label: 'Sold',     data: {$sold_data}, backgroundColor: 'rgba(249,115,22,.8)', borderRadius: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { ...maxTicks({$days_labels}, 14), font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { font: { size: 10 } } }
            }
        }
    });
}

// ── 2. Cumulative net change line ────────────────────────────
const cumCtx = document.getElementById('cumChart');
if (cumCtx) {
    new Chart(cumCtx, {
        type: 'line',
        data: {
            labels: {$days_labels},
            datasets: [{
                label: 'Cumulative Net Stock Change',
                data: {$cumul_data},
                borderColor: '#0d3b8e',
                backgroundColor: 'rgba(13,59,142,.08)',
                fill: true, tension: .4,
                pointRadius: {$days_labels}.length > 60 ? 0 : 3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { ...maxTicks({$days_labels}, 14), font: { size: 10 } } },
                y: { ticks: { font: { size: 10 } } }
            }
        }
    });
}

// ── 3. Daily revenue line ─────────────────────────────────────
const revCtx = document.getElementById('revChart');
if (revCtx) {
    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: {$rev_labels},
            datasets: [{
                label: 'Sales Revenue (KES)',
                data: {$rev_data},
                borderColor: '#f97316',
                backgroundColor: 'rgba(249,115,22,.1)',
                fill: true, tension: .4,
                pointRadius: {$rev_labels}.length > 60 ? 0 : 3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => 'KES ' + ctx.parsed.y.toLocaleString('en-KE', { minimumFractionDigits: 0 })
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { ...maxTicks({$rev_labels}, 14), font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => 'KES ' + v.toLocaleString() } }
            }
        }
    });
}

// ── 4. Stock by category donut ────────────────────────────────
const catCtx = document.getElementById('catChart');
if (catCtx && {$cat_labels}.length) {
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: {$cat_labels},
            datasets: [{ data: {$cat_values}, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } } },
            cutout: '62%'
        }
    });
}

// ── 5. Monthly revenue bar ────────────────────────────────────
const monCtx = document.getElementById('monChart');
if (monCtx && {$mon_labels}.length) {
    new Chart(monCtx, {
        type: 'bar',
        data: {
            labels: {$mon_labels},
            datasets: [{
                label: 'Monthly Revenue (KES)',
                data: {$mon_revenue},
                backgroundColor: 'rgba(13,59,142,.75)',
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, callback: v => 'KES ' + (v/1000).toFixed(0) + 'k' } }
            }
        }
    });
}

// ── Quick date presets ────────────────────────────────────────
document.querySelectorAll('[data-days]').forEach(btn => {
    btn.addEventListener('click', function () {
        const days = parseInt(this.dataset.days);
        const to   = new Date();
        const from = new Date();
        from.setDate(to.getDate() - (days - 1));
        const fmt = d => d.toISOString().split('T')[0];
        document.getElementById('from').value = fmt(from);
        document.getElementById('to').value   = fmt(to);
        document.getElementById('filterForm').submit();
    });
});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start mb-3">
    <i class="bi bi-x-circle-fill mt-1"></i>
    <div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div>
</div>
<?php endif; ?>

<!-- ── KPI Cards ─────────────────────────────────────────────── -->
<div class="row g-3 mb-1">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-blue">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Products</p>
                <div class="kpi-icon c-blue"><i class="bi bi-box-seam"></i></div>
            </div>
            <p class="kpi-value"><?= number_format($kpis['total_products']) ?></p>
            <p class="kpi-sub">active SKUs</p>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-teal">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Units in Stock</p>
                <div class="kpi-icon c-teal"><i class="bi bi-layers"></i></div>
            </div>
            <p class="kpi-value"><?= number_format($kpis['total_units']) ?></p>
            <p class="kpi-sub">across all locations</p>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-blue">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Stock Cost Value</p>
                <div class="kpi-icon c-blue"><i class="bi bi-wallet2"></i></div>
            </div>
            <p class="kpi-value" style="font-size:1.2rem;">KES <?= number_format($kpis['stock_cost_value'], 0) ?></p>
            <p class="kpi-sub">at purchase price</p>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-orange">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Stock Sell Value</p>
                <div class="kpi-icon c-orange"><i class="bi bi-currency-dollar"></i></div>
            </div>
            <p class="kpi-value" style="font-size:1.2rem;">KES <?= number_format($kpis['stock_sell_value'], 0) ?></p>
            <p class="kpi-sub">at selling price</p>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-green">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Today's Sales</p>
                <div class="kpi-icon c-green"><i class="bi bi-receipt"></i></div>
            </div>
            <p class="kpi-value" style="font-size:1.2rem;">KES <?= number_format($kpis['today_sales'], 0) ?></p>
            <p class="kpi-sub"><?= date('d M Y') ?></p>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card c-red">
            <div class="kpi-top">
                <p class="kpi-label mb-0">Low / Out</p>
                <div class="kpi-icon c-red"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
            <p class="kpi-value"><?= $kpis['low_stock'] ?> / <?= $kpis['out_of_stock'] ?></p>
            <p class="kpi-sub"><a href="/low-stock.php" class="text-danger text-decoration-none">view alerts →</a></p>
        </div>
    </div>
</div>

<!-- ── Date filter ───────────────────────────────────────────── -->
<p class="section-title">Stock Trend Analysis</p>

<form id="filterForm" method="GET" action="" class="filter-bar">
    <div>
        <label>From</label>
        <input type="date" id="from" name="from" class="form-control" value="<?= $from ?>">
    </div>
    <div>
        <label>To</label>
        <input type="date" id="to" name="to" class="form-control" value="<?= $to ?>">
    </div>
    <div class="align-self-end">
        <button type="submit" class="btn btn-ck"><i class="bi bi-funnel me-1"></i>Apply</button>
    </div>
    <div class="align-self-end date-quick">
        <button type="button" class="btn" data-days="7">7D</button>
        <button type="button" class="btn" data-days="30">30D</button>
        <button type="button" class="btn" data-days="90">90D</button>
        <button type="button" class="btn" data-days="180">6M</button>
    </div>
    <div class="ms-auto align-self-end">
        <div class="d-flex gap-2 flex-wrap">
            <span class="kpi-badge bg-primary-subtle text-primary"><i class="bi bi-arrow-down-circle me-1"></i>Received: <?= number_format($total_recv) ?></span>
            <span class="kpi-badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-arrow-up-circle me-1"></i>Sold: <?= number_format($total_sold) ?></span>
            <span class="kpi-badge <?= $net_change >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                Net: <?= ($net_change >= 0 ? '+' : '') . number_format($net_change) ?>
            </span>
        </div>
    </div>
</form>

<!-- ── Main charts row ───────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-bar-chart-fill" style="color:var(--ck-blue)"></i> Daily Units — Received vs Sold</h6>
                <span class="badge-cat"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap" style="height:260px;">
                    <canvas id="movChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-graph-up" style="color:var(--ck-blue)"></i> Cumulative Net Change</h6>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap" style="height:260px;">
                    <canvas id="cumChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Revenue charts row ────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-graph-up-arrow" style="color:var(--ck-orange)"></i> Daily Sales Revenue</h6>
                <span class="fw-semibold small" style="color:var(--ck-orange)">KES <?= number_format($total_rev, 0) ?> total</span>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap" style="height:240px;">
                    <canvas id="revChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-calendar3" style="color:var(--ck-blue)"></i> Monthly Revenue (6 months)</h6>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap" style="height:240px;">
                    <canvas id="monChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Category & top products ──────────────────────────────── -->
<p class="section-title">Inventory Breakdown</p>

<div class="row g-3 mb-3">

    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-pie-chart-fill" style="color:var(--ck-orange)"></i> Stock by Category</h6>
            </div>
            <div class="dash-card-body">
                <?php if (empty($categories)): ?>
                    <p class="text-muted text-center py-4">No data.</p>
                <?php else: ?>
                    <div class="chart-wrap" style="height:260px;"><canvas id="catChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-trophy-fill" style="color:var(--ck-orange)"></i> Top Products by Stock Value</h6>
            </div>
            <div class="dash-card-body p-0">
                <?php if (empty($top_value)): ?>
                    <p class="text-muted text-center py-4">No data.</p>
                <?php else:
                    $max_val = max(array_column($top_value, 'stock_value'));
                ?>
                <div class="table-responsive">
                    <table class="table dash-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Cost/Unit</th>
                                <th class="text-end">Stock Value</th>
                                <th style="width:100px">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($top_value as $i => $r): ?>
                            <?php $pct = $max_val > 0 ? ($r['stock_value'] / $max_val * 100) : 0; ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?= $i + 1 ?></td>
                                <td class="fw-semibold" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($r['product']) ?>
                                </td>
                                <td class="text-end"><?= number_format((float)$r['qty']) ?></td>
                                <td class="text-end text-muted">KES <?= number_format((float)$r['cost_price'], 0) ?></td>
                                <td class="text-end fw-bold" style="color:var(--ck-blue)">KES <?= number_format((float)$r['stock_value'], 0) ?></td>
                                <td>
                                    <div class="stock-bar-wrap">
                                        <div class="stock-bar">
                                            <div class="stock-bar-fill" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <span class="text-muted" style="font-size:.7rem;min-width:28px"><?= round($pct) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ── Low stock alerts ──────────────────────────────────────── -->
<?php if (!empty($low_stock)): ?>
<p class="section-title">Low Stock Alerts</p>
<div class="dash-card mb-3">
    <div class="dash-card-header">
        <h6><i class="bi bi-exclamation-triangle-fill text-warning"></i> Items At or Below Alert Quantity</h6>
        <a href="/low-stock.php" class="btn btn-ck-orange btn-sm">View All</a>
    </div>
    <div class="dash-card-body p-0">
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-center">Alert Qty</th>
                        <th class="text-center">In Stock</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($low_stock as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
                        <td><span class="badge-cat"><?= htmlspecialchars($r['category'] ?? '—') ?></span></td>
                        <td class="text-center"><?= number_format((float)$r['alert_quantity']) ?></td>
                        <td class="text-center fw-bold <?= $r['qty'] <= 0 ? 'text-danger' : 'text-warning' ?>"><?= number_format((float)$r['qty']) ?></td>
                        <td class="text-center">
                            <?php if ($r['qty'] <= 0): ?>
                                <span class="badge-out">Out of Stock</span>
                            <?php elseif ($r['qty'] / $r['alert_quantity'] <= 0.5): ?>
                                <span class="badge-low">Critical</span>
                            <?php else: ?>
                                <span class="badge-low">Low</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
