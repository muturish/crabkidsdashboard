<?php
require_once __DIR__ . '/bootstrap.php';

[$fromDate, $toDate] = resolve_date_range();
$fromDateOnly = substr($fromDate, 0, 10);
$toDateOnly = substr($toDate, 0, 10);

$kpis = get_overview_kpis($pdo, $bizId, $fromDate, $toDate);
$series = get_stock_growth_series($pdo, $bizId, $fromDate, $toDate);
$byCategory = get_stock_by_category($pdo, $bizId);
$lowStock = get_low_stock_items($pdo, $bizId, 5);
$outOfStock = get_out_of_stock_items($pdo, $bizId, 1000);

$pageTitle = 'Overview';
$activePage = 'overview';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">Overview</div>
<div class="page-subheading">Stock and movement summary for CrabKids Kenya Co.</div>

<?php require __DIR__ . '/includes/date-filter.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Units in stock</div>
                    <div class="kpi-value"><?= format_number($kpis['total_units_on_hand']) ?></div>
                </div>
                <div class="kpi-icon navy"><i class="bi bi-boxes"></i></div>
            </div>
            <div class="kpi-sub">Across all locations, right now</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Stock value (cost)</div>
                    <div class="kpi-value"><?= format_kes($kpis['stock_value_cost']) ?></div>
                </div>
                <div class="kpi-icon coral"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="kpi-sub">At purchase price</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Units received</div>
                    <div class="kpi-value"><?= format_number($kpis['units_in']) ?></div>
                </div>
                <div class="kpi-icon green"><i class="bi bi-arrow-down-circle"></i></div>
            </div>
            <div class="kpi-sub">In selected period</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Units sold</div>
                    <div class="kpi-value"><?= format_number($kpis['units_out']) ?></div>
                </div>
                <div class="kpi-icon amber"><i class="bi bi-arrow-up-circle"></i></div>
            </div>
            <div class="kpi-sub">In selected period</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="panel h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="panel-title">Stock growth</div>
                    <div class="panel-subtitle">Cumulative net stock change (received − sold ± adjustments)</div>
                </div>
                <a href="stock-growth.php?from=<?= $fromDateOnly ?>&to=<?= $toDateOnly ?>" class="btn btn-sm btn-outline-dark">Full view</a>
            </div>
            <canvas id="overviewGrowthChart" height="90"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel h-100">
            <div class="panel-title">Stock by category</div>
            <div class="panel-subtitle">Units currently on hand</div>
            <canvas id="categoryChart" height="220"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="panel-title">Low stock — top 5</div>
                    <div class="panel-subtitle">Closest to running out</div>
                </div>
                <a href="low-stock.php" class="btn btn-sm btn-outline-dark">View all</a>
            </div>
            <?php if (empty($lowStock)): ?>
                <p class="text-muted small mb-0">Nothing is below its alert quantity right now.</p>
            <?php else: ?>
                <table class="table table-sm table-low-stock mb-0">
                    <thead><tr><th>Product</th><th class="text-end">On hand</th><th class="text-end">Alert at</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStock as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['product_name']) ?> <span class="text-muted small"><?= htmlspecialchars($row['variation_name']) ?></span></td>
                            <td class="text-end"><?= format_number((float)$row['qty_available']) ?></td>
                            <td class="text-end text-muted"><?= format_number((float)$row['alert_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel">
            <div class="panel-title">Out of stock</div>
            <div class="panel-subtitle">Items at zero or negative quantity</div>
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon coral" style="width:56px;height:56px;font-size:1.4rem;"><i class="bi bi-x-octagon"></i></div>
                <div>
                    <div class="kpi-value mb-0"><?= format_number(count($outOfStock)) ?></div>
                    <div class="text-muted small">variation(s) currently unavailable to sell</div>
                </div>
            </div>
            <?php if (!empty($outOfStock)): ?>
                <a href="low-stock.php#out-of-stock" class="btn btn-sm btn-outline-dark mt-3">View list</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const growthLabels = <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['date'])), $series)) ?>;
const growthRunning = <?= json_encode(array_map(fn($r) => $r['running_total'], $series)) ?>;

new Chart(document.getElementById('overviewGrowthChart'), {
    type: 'line',
    data: {
        labels: growthLabels,
        datasets: [{
            label: 'Cumulative net stock change',
            data: growthRunning,
            borderColor: '#ff6b5e',
            backgroundColor: 'rgba(255,107,94,0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            borderWidth: 2.5,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: '#eceef3' } }
        }
    }
});

const categoryLabels = <?= json_encode(array_map(fn($r) => $r['category_name'], $byCategory)) ?>;
const categoryUnits = <?= json_encode(array_map(fn($r) => (float)$r['total_units'], $byCategory)) ?>;

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryUnits,
            backgroundColor: ['#ff6b5e', '#1f2a44', '#2bb673', '#e0a324', '#7c83fd', '#e25555', '#5cc8d7', '#a78bfa'],
            borderWidth: 0,
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
