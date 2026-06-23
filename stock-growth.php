<?php
require_once __DIR__ . '/bootstrap.php';

[$fromDate, $toDate] = resolve_date_range();
$fromDateOnly = substr($fromDate, 0, 10);
$toDateOnly = substr($toDate, 0, 10);

$series = get_stock_growth_series($pdo, $bizId, $fromDate, $toDate);
$topRestocked = get_top_restocked_products($pdo, $bizId, $fromDate, $toDate, 10);

$totalIn = array_sum(array_column($series, 'units_in'));
$totalOut = array_sum(array_column($series, 'units_out'));
$totalAdj = array_sum(array_column($series, 'adjustment'));
$netChange = $totalIn - $totalOut + $totalAdj;
$endingRunningTotal = end($series)['running_total'] ?? 0;

$pageTitle = 'Stock Growth';
$activePage = 'growth';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">Stock Growth</div>
<div class="page-subheading">How your inventory levels changed over time — restocks vs. depletion.</div>

<?php require __DIR__ . '/includes/date-filter.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Units received</div>
            <div class="kpi-value text-success"><?= format_number($totalIn) ?></div>
            <div class="kpi-sub">Restocked in period</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Units sold</div>
            <div class="kpi-value" style="color:#e0a324;"><?= format_number($totalOut) ?></div>
            <div class="kpi-sub">Depleted in period</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Manual adjustments</div>
            <div class="kpi-value"><?= ($totalAdj >= 0 ? '+' : '') . format_number($totalAdj) ?></div>
            <div class="kpi-sub">Corrections, write-offs, etc.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="kpi-label">Net change</div>
            <div class="kpi-value <?= $netChange >= 0 ? 'trend-up' : 'trend-down' ?>">
                <?= ($netChange >= 0 ? '+' : '') . format_number($netChange) ?>
            </div>
            <div class="kpi-sub">Over selected period</div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-title">Daily stock movement</div>
    <div class="panel-subtitle">Bars show daily in/out; the line tracks cumulative net change since the start of the period</div>
    <canvas id="growthDetailChart" height="100"></canvas>
</div>

<div class="panel">
    <div class="panel-title">Most restocked products</div>
    <div class="panel-subtitle">By units received in this period</div>
    <?php if (empty($topRestocked)): ?>
        <p class="text-muted small mb-0">No purchases recorded in this period.</p>
    <?php else: ?>
        <table class="table table-sm table-low-stock mb-0">
            <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="text-end">Units received</th>
                <th class="text-end">Amount spent</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topRestocked as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['product_sku']) ?></td>
                    <td class="text-end"><?= format_number((float)$row['units_in']) ?></td>
                    <td class="text-end"><?= format_kes((float)$row['amount_spent']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
const labels = <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['date'])), $series)) ?>;
const unitsIn = <?= json_encode(array_map(fn($r) => $r['units_in'], $series)) ?>;
const unitsOut = <?= json_encode(array_map(fn($r) => -$r['units_out'], $series)) ?>;
const runningTotal = <?= json_encode(array_map(fn($r) => $r['running_total'], $series)) ?>;

new Chart(document.getElementById('growthDetailChart'), {
    data: {
        labels: labels,
        datasets: [
            {
                type: 'bar',
                label: 'Received',
                data: unitsIn,
                backgroundColor: '#2bb673',
                borderRadius: 3,
                order: 2,
            },
            {
                type: 'bar',
                label: 'Sold',
                data: unitsOut,
                backgroundColor: '#e0a324',
                borderRadius: 3,
                order: 2,
            },
            {
                type: 'line',
                label: 'Cumulative net change',
                data: runningTotal,
                borderColor: '#ff6b5e',
                backgroundColor: 'rgba(255,107,94,0.08)',
                borderWidth: 2.5,
                pointRadius: 0,
                fill: false,
                tension: 0.25,
                order: 1,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        plugins: { legend: { position: 'bottom' } },
        scales: {
            x: { grid: { display: false }, stacked: true },
            y: { grid: { color: '#eceef3' }, stacked: true, title: { display: true, text: 'Daily units' } },
            y1: { position: 'right', grid: { display: false }, title: { display: true, text: 'Cumulative units' } }
        }
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
