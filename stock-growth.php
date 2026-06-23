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
    data: { labels:days, datasets:[
      { label:'Received', data:recv, backgroundColor:'rgba(29,78,216,.75)' },
      { label:'Sold',     data:sold, backgroundColor:'rgba(249,115,22,.75)' }
    ]},
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}}, scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{beginAtZero:true}} }
  });

  new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: { labels:days, datasets:[{ label:'Cumulative Net Change', data:cumul, borderColor:'#1d4ed8', backgroundColor:'rgba(29,78,216,.08)', fill:true, pointRadius:days.length>60?0:3 }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}}, scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{}} }
  });
}

document.querySelectorAll('[data-days]').forEach(function(btn){
  btn.addEventListener('click',function(){
    var d=parseInt(this.dataset.days),to=new Date(),from=new Date();
    from.setDate(to.getDate()-(d-1));
    function fmt(x){return x.toISOString().split('T')[0];}
    document.getElementById('from').value=fmt(from);
    document.getElementById('to').value=fmt(to);
    document.getElementById('filterForm').submit();
  });
});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert error"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1>
    <p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p>
  </div>
</div>

<!-- ── Summary KPIs ──────────────────────────────────────────── -->
<div class="row g-3 mb-4" style="max-width:560px;">
  <div class="col-4">
    <div class="ck-kpi kpi-blue h-100">
      <div class="ck-kpi-top"><p class="ck-kpi-label">Total Received</p><span class="ck-kpi-icon"><i class="bi bi-arrow-down-circle"></i></span></div>
      <div class="ck-kpi-value"><?= number_format($total_recv) ?></div>
      <div class="ck-kpi-sub">units in period</div>
    </div>
  </div>
  <div class="col-4">
    <div class="ck-kpi kpi-orange h-100">
      <div class="ck-kpi-top"><p class="ck-kpi-label">Total Sold</p><span class="ck-kpi-icon"><i class="bi bi-arrow-up-circle"></i></span></div>
      <div class="ck-kpi-value"><?= number_format($total_sold) ?></div>
      <div class="ck-kpi-sub">units in period</div>
    </div>
  </div>
  <div class="col-4">
    <div class="ck-kpi <?= $net>=0?'kpi-green':'kpi-red' ?> h-100">
      <div class="ck-kpi-top"><p class="ck-kpi-label">Net Change</p><span class="ck-kpi-icon"><i class="bi bi-<?= $net>=0?'graph-up':'graph-down' ?>"></i></span></div>
      <div class="ck-kpi-value"><?= ($net>=0?'+':'').number_format($net) ?></div>
      <div class="ck-kpi-sub">cumulative</div>
    </div>
  </div>
</div>

<!-- ── Filter ────────────────────────────────────────────────── -->
<form id="filterForm" method="GET" action="" class="ck-filter-bar">
  <div class="ck-date-group">
    <input type="date" id="from" name="from" value="<?= $from ?>">
    <span class="ck-date-sep">→</span>
    <input type="date" id="to" name="to" value="<?= $to ?>">
  </div>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets">
    <button type="button" class="ck-preset-btn" data-days="7">7D</button>
    <button type="button" class="ck-preset-btn" data-days="30">30D</button>
    <button type="button" class="ck-preset-btn" data-days="90">90D</button>
    <button type="button" class="ck-preset-btn" data-days="180">6M</button>
  </div>
  <a href="stock-growth.php" class="btn btn-ck-ghost btn-sm ms-1">Reset</a>
</form>

<!-- ── Charts ────────────────────────────────────────────────── -->
<?php if ($rows): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-bar-chart-fill"></i></span> Daily Received vs Sold</h6>
        <span class="text-muted small"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:260px;"><canvas id="barChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-graph-up"></i></span> Cumulative Net Change</h6>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:260px;"><canvas id="lineChart"></canvas></div>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card mb-3">
  <div class="card-body">
    <div class="ck-empty">
      <div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div>
      <p class="ck-empty-title">No movement data</p>
      <p class="ck-empty-desc">No stock movement was recorded in this date range.</p>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Most restocked ─────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-section-title">Most Restocked Products</span>
  <span class="text-muted small"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<div class="card mb-3">
  <?php if (empty($top)): ?>
  <div class="card-body">
    <div class="ck-empty">
      <div class="ck-empty-icon"><i class="bi bi-inbox"></i></div>
      <p class="ck-empty-title">No purchase data</p>
      <p class="ck-empty-desc">No restocking records found for this period.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th class="ck-td-rank">#</th>
          <th>Product</th>
          <th>Variation</th>
          <th class="text-end">Units Received</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($top as $i => $r): ?>
        <tr>
          <td class="ck-td-rank"><span class="ck-rank <?= $i===0?'r1':($i===1?'r2':($i===2?'r3':'')) ?>"><?= $i+1 ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td class="text-end fw-bold" style="color:var(--ck-blue)"><?= number_format((float)$r['total_received']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Daily breakdown ───────────────────────────────────────── -->
<?php if ($rows): ?>
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-section-title">Daily Breakdown</span>
</div>
<div class="card">
  <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th class="text-end">Received</th>
          <th class="text-end">Sold</th>
          <th class="text-end">Net</th>
          <th class="text-end">Cumulative</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($rows) as $r): $n=(int)$r['net']; ?>
        <tr>
          <td class="fw-medium"><?= $r['day'] ?></td>
          <td class="text-end"><?= number_format($r['received']) ?></td>
          <td class="text-end"><?= number_format($r['sold']) ?></td>
          <td class="text-end fw-bold <?= $n>0?'text-success':($n<0?'text-danger':'text-muted') ?>"><?= ($n>0?'+':'').number_format($n) ?></td>
          <td class="text-end fw-semibold"><?= number_format($r['cumulative']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
