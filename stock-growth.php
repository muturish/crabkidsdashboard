<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Stock Trend';
$page_subtitle = 'Units received vs sold over time';

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

try {
    $rows     = get_stock_growth($from, $to);
    $top      = get_top_restocked($from, $to);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $rows = $top = [];
}

$total_recv = array_sum(array_column($rows, 'received'));
$total_sold = array_sum(array_column($rows, 'sold'));
$net        = !empty($rows) ? end($rows)['cumulative'] : 0;

$j_days  = json_encode(array_column($rows, 'day'));
$j_recv  = json_encode(array_column($rows, 'received'));
$j_sold  = json_encode(array_column($rows, 'sold'));
$j_cumul = json_encode(array_column($rows, 'cumulative'));

$inline_scripts = <<<JS
var days  = {$j_days};
var recv  = {$j_recv};
var sold  = {$j_sold};
var cumul = {$j_cumul};

if (days.length) {
  new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
      labels: days,
      datasets: [
        { label: 'Received', data: recv, backgroundColor: 'rgba(29,78,216,.75)' },
        { label: 'Sold',     data: sold, backgroundColor: 'rgba(249,115,22,.75)' }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 14 } },
        y: { beginAtZero: true }
      }
    }
  });

  new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Cumulative Net Change',
        data: cumul,
        borderColor: '#1d4ed8',
        backgroundColor: 'rgba(29,78,216,.08)',
        fill: true,
        pointRadius: days.length > 60 ? 0 : 3
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 14 } },
        y: {}
      }
    }
  });
}

document.querySelectorAll('[data-days]').forEach(function(btn){
  btn.addEventListener('click', function(){
    var days = parseInt(this.dataset.days);
    var to   = new Date();
    var from = new Date();
    from.setDate(to.getDate() - (days - 1));
    function fmt(d){ return d.toISOString().split('T')[0]; }
    document.getElementById('from').value = fmt(from);
    document.getElementById('to').value   = fmt(to);
    document.getElementById('filterForm').submit();
  });
});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="alert-banner alert-error">
  <i class="bi bi-x-circle-fill"></i>
  <div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div>
</div>
<?php endif; ?>

<!-- ── Page header ───────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></div>
  </div>
</div>

<!-- ── Summary KPIs ─────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;margin-bottom:20px;">
  <div class="kpi-card kpi-blue">
    <div class="kpi-card-top">
      <span class="kpi-label">Total Received</span>
      <span class="kpi-icon"><i class="bi bi-arrow-down-circle"></i></span>
    </div>
    <div class="kpi-value"><?= number_format($total_recv) ?></div>
    <div class="kpi-sub">units in period</div>
  </div>
  <div class="kpi-card kpi-orange">
    <div class="kpi-card-top">
      <span class="kpi-label">Total Sold</span>
      <span class="kpi-icon"><i class="bi bi-arrow-up-circle"></i></span>
    </div>
    <div class="kpi-value"><?= number_format($total_sold) ?></div>
    <div class="kpi-sub">units in period</div>
  </div>
  <div class="kpi-card <?= $net >= 0 ? 'kpi-green' : 'kpi-red' ?>">
    <div class="kpi-card-top">
      <span class="kpi-label">Net Change</span>
      <span class="kpi-icon"><i class="bi bi-<?= $net >= 0 ? 'graph-up' : 'graph-down' ?>"></i></span>
    </div>
    <div class="kpi-value"><?= ($net >= 0 ? '+' : '') . number_format($net) ?></div>
    <div class="kpi-sub">cumulative</div>
  </div>
</div>

<!-- ── Filter ───────────────────────────────────────────────── -->
<form id="filterForm" method="GET" action="" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
  <div class="date-range-group">
    <input type="date" id="from" name="from" value="<?= $from ?>">
    <span class="date-range-sep">→</span>
    <input type="date" id="to" name="to" value="<?= $to ?>">
  </div>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
  <div class="preset-group">
    <button type="button" class="preset-btn" data-days="7">7D</button>
    <button type="button" class="preset-btn" data-days="30">30D</button>
    <button type="button" class="preset-btn" data-days="90">90D</button>
    <button type="button" class="preset-btn" data-days="180">6M</button>
  </div>
  <a href="stock-growth.php" class="btn btn-ghost btn-sm">Reset</a>
</form>

<!-- ── Charts ───────────────────────────────────────────────── -->
<?php if ($rows): ?>
<div class="grid grid-12-8" style="margin-bottom:16px;">
  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-bar-chart-fill"></i></span> Daily Received vs Sold</h6>
      <span class="card-meta"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;"><canvas id="barChart"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-graph-up"></i></span> Cumulative Net Change</h6>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;"><canvas id="lineChart"></canvas></div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-bar-chart"></i></div>
      <div class="empty-state-title">No movement data</div>
      <div class="empty-state-desc">No stock movement was recorded in this date range.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Most restocked ────────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Most Restocked Products</span>
  <span class="card-meta"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<div class="card" style="margin-bottom:16px;">
  <?php if (empty($top)): ?>
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
      <div class="empty-state-title">No purchase data</div>
      <div class="empty-state-desc">No restocking records found for this period.</div>
    </div>
  </div>
  <?php else: ?>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th class="td-rank">#</th>
          <th>Product</th>
          <th>Variation</th>
          <th style="text-align:right;">Units Received</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($top as $i => $r): ?>
        <tr>
          <td class="td-rank"><span class="rank-badge <?= $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'')) ?>"><?= $i+1 ?></span></td>
          <td class="fw-600"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="chip chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td style="text-align:right;" class="fw-700 text-blue"><?= number_format((float)$r['total_received']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Daily breakdown ───────────────────────────────────────── -->
<?php if ($rows): ?>
<div class="section-header">
  <span class="section-title">Daily Breakdown</span>
</div>
<div class="card">
  <div class="card-body-flush table-scroll table-scroll-y" style="max-height:360px;">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th style="text-align:right;">Received</th>
          <th style="text-align:right;">Sold</th>
          <th style="text-align:right;">Net</th>
          <th style="text-align:right;">Cumulative</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($rows) as $r):
        $n = (int)$r['net'];
      ?>
        <tr>
          <td class="fw-500"><?= $r['day'] ?></td>
          <td style="text-align:right;"><?= number_format($r['received']) ?></td>
          <td style="text-align:right;"><?= number_format($r['sold']) ?></td>
          <td style="text-align:right;" class="fw-700 <?= $n > 0 ? 'text-success' : ($n < 0 ? 'text-danger' : 'text-tertiary') ?>">
            <?= ($n > 0 ? '+' : '') . number_format($n) ?>
          </td>
          <td style="text-align:right;" class="fw-600"><?= number_format($r['cumulative']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
