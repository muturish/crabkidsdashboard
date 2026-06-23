<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Overview';
$page_subtitle = 'Your live stock snapshot for today';

// Fetch data
try {
    $kpis       = get_kpis();
    $categories = get_stock_by_category();
    $recent     = get_recent_sales();
    $db_error   = null;
} catch (Exception $e) {
    $db_error   = $e->getMessage();
    $kpis       = ['total_products' => 0, 'in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'stock_value' => 0];
    $categories = [];
    $recent     = [];
}

// Chart data
$cat_labels = json_encode(array_column($categories, 'category'));
$cat_values = json_encode(array_column($categories, 'total_qty'));

$inline_scripts = <<<JS
// ── Category donut ─────────────────────────────────────────
const catLabels = {$cat_labels};
const catValues = {$cat_values};

const palette = [
    '#0d3b8e','#1a56c4','#f97316','#ea6c0a','#10b981',
    '#3b82f6','#f59e0b','#6366f1','#ec4899','#14b8a6'
];

if (catLabels.length) {
    const ctx = document.getElementById('catChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{
                data: catValues,
                backgroundColor: palette,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 12 }, padding: 14 } }
            },
            cutout: '65%'
        }
    });
}
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div>
        <strong>Database not connected.</strong> Please create your <code>.env</code> file from <code>.env.example</code>.<br>
        <small class="text-muted"><?= htmlspecialchars($db_error) ?></small>
    </div>
</div>
<?php endif; ?>

<!-- ── KPI row ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="kpi-card blue">
            <div class="kpi-icon blue"><i class="bi bi-box-seam"></i></div>
            <div>
                <p class="kpi-label">Total Products</p>
                <p class="kpi-value"><?= number_format($kpis['total_products']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card green">
            <div class="kpi-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <p class="kpi-label">In Stock</p>
                <p class="kpi-value"><?= number_format($kpis['in_stock']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card orange">
            <div class="kpi-icon orange"><i class="bi bi-exclamation-circle"></i></div>
            <div>
                <p class="kpi-label">Low Stock</p>
                <p class="kpi-value"><?= number_format($kpis['low_stock']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="kpi-card red">
            <div class="kpi-icon red"><i class="bi bi-x-circle"></i></div>
            <div>
                <p class="kpi-label">Out of Stock</p>
                <p class="kpi-value"><?= number_format($kpis['out_of_stock']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Stock value banner ───────────────────────────────────── -->
<div class="dash-card mb-4 p-3 d-flex align-items-center gap-3"
     style="border-left: 5px solid var(--ck-orange); background: linear-gradient(90deg,#fff7ed,#fff);">
    <div class="kpi-icon orange" style="width:56px;height:56px;font-size:1.7rem;">
        <i class="bi bi-currency-dollar"></i>
    </div>
    <div>
        <p class="kpi-label mb-0">Total Inventory Value (Sell Price)</p>
        <p class="kpi-value" style="font-size:2rem;">
            KES <?= number_format($kpis['stock_value'], 2) ?>
        </p>
    </div>
</div>

<!-- ── Category donut + Recent sales ───────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-lg-5">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-pie-chart-fill me-2 text-orange" style="color:var(--ck-orange)"></i>Stock by Category</h6>
            </div>
            <div class="dash-card-body">
                <?php if (empty($categories)): ?>
                    <p class="text-muted text-center py-4">No data available.</p>
                <?php else: ?>
                    <div class="chart-wrap"><canvas id="catChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6><i class="bi bi-receipt me-2" style="color:var(--ck-blue)"></i>Recent Sales</h6>
                <a href="/stock-growth.php" class="btn btn-sm btn-primary-ck">View All</a>
            </div>
            <div class="dash-card-body p-0">
                <?php if (empty($recent)): ?>
                    <p class="text-muted text-center py-4">No recent sales found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table dash-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th class="text-end">Total (KES)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['invoice_no']) ?></span></td>
                                <td><?= htmlspecialchars($r['sale_date']) ?></td>
                                <td><?= (int)$r['items'] ?></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$r['final_total'], 2) ?></td>
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

<!-- ── Quick alert links ─────────────────────────────────────── -->
<div class="row g-3">
    <div class="col-md-6">
        <a href="/low-stock.php" class="text-decoration-none">
            <div class="dash-card p-3 d-flex align-items-center gap-3"
                 style="border-left:4px solid var(--ck-orange);">
                <div class="kpi-icon orange"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <p class="mb-0 fw-700" style="color:var(--ck-orange);font-weight:700;"><?= number_format($kpis['low_stock']) ?> items</p>
                    <p class="mb-0 small text-muted">are running low — click to view</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="/low-stock.php#out-of-stock" class="text-decoration-none">
            <div class="dash-card p-3 d-flex align-items-center gap-3"
                 style="border-left:4px solid #ef4444;">
                <div class="kpi-icon red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <p class="mb-0 fw-700" style="color:#ef4444;font-weight:700;"><?= number_format($kpis['out_of_stock']) ?> variations</p>
                    <p class="mb-0 small text-muted">are out of stock — click to view</p>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
