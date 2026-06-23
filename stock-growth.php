<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Stock Growth';
$page_subtitle = 'Units received vs sold over time';

// Date range
$default_from = date('Y-m-d', strtotime('-30 days'));
$default_to   = date('Y-m-d');
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : $default_from;
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : $default_to;
if ($from > $to) [$from, $to] = [$to, $from];

try {
    $rows      = get_stock_growth($from, $to);
    $top       = get_top_restocked($from, $to);
    $db_error  = null;
} catch (Exception $e) {
    $db_error  = $e->getMessage();
    $rows      = [];
    $top       = [];
}

// Chart data
$days   = json_encode(array_column($rows, 'day'));
$recv   = json_encode(array_column($rows, 'received'));
$sold   = json_encode(array_column($rows, 'sold'));
$cumul  = json_encode(array_column($rows, 'cumulative'));

$inline_scripts = <<<JS
const days  = {$days};
const recv  = {$recv};
const sold  = {$sold};
const cumul = {$cumul};

// ── Bar chart: received vs sold ───────────────────────────────
if (days.length) {
    new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: days,
            datasets: [
                {
                    label: 'Received',
                    data: recv,
                    backgroundColor: 'rgba(13,59,142,.75)',
                    borderRadius: 4
                },
                {
                    label: 'Sold',
                    data: sold,
                    backgroundColor: 'rgba(249,115,22,.75)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 14, font: { size: 11 } } },
                y: { beginAtZero: true, ticks: { font: { size: 11 } } }
            }
        }
    });

    // ── Line chart: cumulative net ────────────────────────────
    new Chart(document.getElementById('lineChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: 'Cumulative Net Change',
                data: cumul,
                borderColor: '#0d3b8e',
                backgroundColor: 'rgba(13,59,142,.1)',
                fill: true,
                tension: .35,
                pointRadius: days.length > 60 ? 0 : 3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 14, font: { size: 11 } } },
                y: { ticks: { font: { size: 11 } } }
            }
        }
    });
}
JS;

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Date filter ──────────────────────────────────────────── -->
<form method="GET" action="" class="filter-bar">
    <div>
        <label>From</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>" max="<?= $to ?>">
    </div>
    <div>
        <label>To</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>" min="<?= $from ?>">
    </div>
    <button type="submit" class="btn btn-primary-ck">
        <i class="bi bi-funnel me-1"></i>Apply
    </button>
    <a href="stock-growth.php" class="btn btn-outline-secondary btn-sm align-self-end">Reset</a>
</form>

<?php if ($db_error): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
    Database not connected: <?= htmlspecialchars($db_error) ?>
</div>
<?php endif; ?>

<!-- ── Summary pills ───────────────────────────────────────── -->
<?php if ($rows): ?>
<?php
    $total_recv = array_sum(array_column($rows, 'received'));
    $total_sold = array_sum(array_column($rows, 'sold'));
    $net        = end($rows)['cumulative'];
?>
<div class="d-flex flex-wrap gap-3 mb-4">
    <div class="kpi-card blue p-3" style="min-width:160px">
        <div class="kpi-icon blue"><i class="bi bi-arrow-down-circle"></i></div>
        <div>
            <p class="kpi-label">Total Received</p>
            <p class="kpi-value"><?= number_format($total_recv) ?></p>
        </div>
    </div>
    <div class="kpi-card orange p-3" style="min-width:160px">
        <div class="kpi-icon orange"><i class="bi bi-arrow-up-circle"></i></div>
        <div>
            <p class="kpi-label">Total Sold</p>
            <p class="kpi-value"><?= number_format($total_sold) ?></p>
        </div>
    </div>
    <div class="kpi-card <?= $net >= 0 ? 'green' : 'red' ?> p-3" style="min-width:160px">
        <div class="kpi-icon <?= $net >= 0 ? 'green' : 'red' ?>">
            <i class="bi bi-<?= $net >= 0 ? 'graph-up' : 'graph-down' ?>"></i>
        </div>
        <div>
            <p class="kpi-label">Net Change</p>
            <p class="kpi-value"><?= ($net >= 0 ? '+' : '') . number_format($net) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Charts ──────────────────────────────────────────────── -->
<?php if ($rows): ?>
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="dash-card">
            <div class="dash-card-header">
                <h6><i class="bi bi-bar-chart-fill me-2" style="color:var(--ck-blue)"></i>Daily Received vs Sold</h6>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap"><canvas id="barChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="dash-card">
            <div class="dash-card-header">
                <h6><i class="bi bi-graph-up me-2" style="color:var(--ck-blue)"></i>Cumulative Net Change</h6>
            </div>
            <div class="dash-card-body">
                <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="dash-card p-4 text-center text-muted mb-4">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>No stock movement data for this period.
</div>
<?php endif; ?>

<!-- ── Top restocked ────────────────────────────────────────── -->
<div class="dash-card mb-4">
    <div class="dash-card-header">
        <h6><i class="bi bi-arrow-repeat me-2" style="color:var(--ck-orange)"></i>Most Restocked Products</h6>
        <span class="badge" style="background:var(--ck-bg);color:var(--ck-blue);"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
    </div>
    <div class="dash-card-body p-0">
        <?php if (empty($top)): ?>
            <p class="text-muted text-center py-4">No purchase data in this period.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Variation</th>
                        <th class="text-end">Units Received</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top as $i => $r): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($r['product']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['variation']) ?></span></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$r['total_received']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Daily breakdown table ────────────────────────────────── -->
<?php if ($rows): ?>
<div class="dash-card">
    <div class="dash-card-header">
        <h6><i class="bi bi-table me-2" style="color:var(--ck-blue)"></i>Daily Breakdown</h6>
    </div>
    <div class="dash-card-body p-0">
        <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
            <table class="table dash-table">
                <thead style="position:sticky;top:0;z-index:1;">
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Sold</th>
                        <th class="text-end">Net</th>
                        <th class="text-end">Cumulative</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($rows) as $r): ?>
                    <?php $net = $r['net']; ?>
                    <tr>
                        <td><?= $r['day'] ?></td>
                        <td class="text-end"><?= number_format($r['received']) ?></td>
                        <td class="text-end"><?= number_format($r['sold']) ?></td>
                        <td class="text-end <?= $net > 0 ? 'text-success' : ($net < 0 ? 'text-danger' : 'text-muted') ?> fw-semibold">
                            <?= ($net > 0 ? '+' : '') . number_format($net) ?>
                        </td>
                        <td class="text-end fw-semibold"><?= number_format($r['cumulative']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
