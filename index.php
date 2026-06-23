<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Overview';
$page_subtitle = 'Live stock & sales snapshot — ' . date('l, d F Y');

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

try {
    $kpis       = get_kpis();
    $movement   = get_daily_stock_movement($from, $to);
    $revenue    = get_daily_revenue($from, $to);
    $categories = get_stock_by_category();
    $top_value  = get_top_stock_value(10);
    $low_stock  = get_low_stock_items();
    $monthly    = get_monthly_summary(6);
    $db_error   = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $kpis = ['total_products'=>0,'total_units'=>0,'stock_cost_value'=>0,'stock_sell_value'=>0,'low_stock'=>0,'out_of_stock'=>0,'today_sales'=>0];
    $movement = $revenue = $categories = $top_value = $low_stock = $monthly = [];
}

$total_recv = array_sum(array_column($movement, 'received'));
$total_sold = array_sum(array_column($movement, 'sold'));
$net_change = !empty($movement) ? end($movement)['cumulative'] : 0;
$total_rev  = array_sum(array_column($revenue, 'revenue'));

$j_days  = json_encode(array_column($movement,   'day'));
$j_recv  = json_encode(array_column($movement,   'received'));
$j_sold  = json_encode(array_column($movement,   'sold'));
$j_cumul = json_encode(array_column($movement,   'cumulative'));
$j_rdays = json_encode(array_column($revenue,    'day'));
$j_rev   = json_encode(array_column($revenue,    'revenue'));
$j_clbl  = json_encode(array_column($categories, 'category'));
$j_cval  = json_encode(array_column($categories, 'total_qty'));
$j_mlbl  = json_encode(array_column($monthly,    'month'));
$j_mrev  = json_encode(array_column($monthly,    'revenue'));

$inline_scripts = <<<JS
var PALETTE = ['#1d4ed8','#f97316','#059669','#0284c7','#7c3aed','#d97706','#db2777','#0f766e','#c2410c','#4338ca'];

// 1. Movement
(function(){
  var ctx = document.getElementById('movChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: {$j_days},
      datasets: [
        { label: 'Received', data: {$j_recv}, backgroundColor: 'rgba(29,78,216,.75)' },
        { label: 'Sold',     data: {$j_sold}, backgroundColor: 'rgba(249,115,22,.75)' }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: { x: { grid:{display:false}, ticks:{maxTicksLimit:14} }, y: {beginAtZero:true} }
    }
  });
})();

// 2. Cumulative
(function(){
  var ctx = document.getElementById('cumChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: {$j_days},
      datasets: [{ label:'Net Stock Change', data:{$j_cumul}, borderColor:'#1d4ed8', backgroundColor:'rgba(29,78,216,.08)', fill:true, pointRadius:{$j_days}.length>60?0:3 }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'top'}},
      scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{}}
    }
  });
})();

// 3. Revenue
(function(){
  var ctx = document.getElementById('revChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: {$j_rdays},
      datasets: [{ label:'Revenue (KES)', data:{$j_rev}, borderColor:'#f97316', backgroundColor:'rgba(249,115,22,.1)', fill:true, pointRadius:{$j_rdays}.length>60?0:3 }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{position:'top'}, tooltip:{callbacks:{label:function(c){return 'KES '+c.parsed.y.toLocaleString();}}} },
      scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{beginAtZero:true,ticks:{callback:function(v){return 'KES '+(v/1000).toFixed(0)+'k';}}}}
    }
  });
})();

// 4. Category donut
(function(){
  var ctx = document.getElementById('catChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: { labels:{$j_clbl}, datasets:[{data:{$j_cval},backgroundColor:PALETTE,borderWidth:2,borderColor:'#fff'}] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'62%', plugins:{legend:{position:'bottom',labels:{padding:14}}} }
  });
})();

// 5. Monthly
(function(){
  var ctx = document.getElementById('monChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels:{$j_mlbl}, datasets:[{label:'Revenue (KES)',data:{$j_mrev},backgroundColor:'rgba(29,78,216,.75)',borderSkipped:false}] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{callback:function(v){return 'KES '+(v/1000).toFixed(0)+'k';}}}}}
  });
})();

// Date presets
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

<!-- ── Page header ──────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1>
    <p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p>
  </div>
</div>

<!-- ── KPI Grid ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-blue h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Active Products</p>
        <span class="ck-kpi-icon"><i class="bi bi-box-seam"></i></span>
      </div>
      <div class="ck-kpi-value"><?= number_format($kpis['total_products']) ?></div>
      <div class="ck-kpi-sub">total SKUs</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-sky h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Units in Stock</p>
        <span class="ck-kpi-icon"><i class="bi bi-layers"></i></span>
      </div>
      <div class="ck-kpi-value"><?= number_format($kpis['total_units']) ?></div>
      <div class="ck-kpi-sub">all locations</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-slate h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Cost Value</p>
        <span class="ck-kpi-icon"><i class="bi bi-wallet2"></i></span>
      </div>
      <div class="ck-kpi-value sm">KES <?= number_format($kpis['stock_cost_value'], 0) ?></div>
      <div class="ck-kpi-sub">at purchase price</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-orange h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Sell Value</p>
        <span class="ck-kpi-icon"><i class="bi bi-currency-dollar"></i></span>
      </div>
      <div class="ck-kpi-value sm">KES <?= number_format($kpis['stock_sell_value'], 0) ?></div>
      <div class="ck-kpi-sub">at selling price</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-green h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Today's Sales</p>
        <span class="ck-kpi-icon"><i class="bi bi-receipt"></i></span>
      </div>
      <div class="ck-kpi-value sm">KES <?= number_format($kpis['today_sales'], 0) ?></div>
      <div class="ck-kpi-sub"><?= date('d M Y') ?></div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-red h-100">
      <div class="ck-kpi-top">
        <p class="ck-kpi-label">Stock Alerts</p>
        <span class="ck-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
      </div>
      <div class="ck-kpi-value"><?= $kpis['low_stock'] ?><span class="fs-6 fw-normal text-muted ms-1">low</span></div>
      <div class="ck-kpi-sub"><?= $kpis['out_of_stock'] ?> out of stock &nbsp;<a href="/low-stock.php" class="text-danger text-decoration-none fw-semibold">View →</a></div>
    </div>
  </div>

</div>

<!-- ── Filter bar ────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
  <span class="ck-section-title">Stock Movement</span>
  <div class="ck-pills">
    <span class="ck-pill"><i class="bi bi-arrow-down-circle text-primary"></i> Received: <strong><?= number_format($total_recv) ?></strong></span>
    <span class="ck-pill"><i class="bi bi-arrow-up-circle" style="color:var(--ck-orange)"></i> Sold: <strong><?= number_format($total_sold) ?></strong></span>
    <span class="ck-pill" style="color:<?= $net_change>=0?'#059669':'#dc2626' ?>">Net: <strong><?= ($net_change>=0?'+':'').number_format($net_change) ?></strong></span>
  </div>
</div>

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
</form>

<!-- ── Charts row 1 ──────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-bar-chart-fill"></i></span> Daily Units — Received vs Sold</h6>
        <span class="text-muted small"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:260px;"><canvas id="movChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-graph-up"></i></span> Cumulative Net Change</h6>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:260px;"><canvas id="cumChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Charts row 2 ──────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon orange"><i class="bi bi-graph-up-arrow"></i></span> Daily Sales Revenue</h6>
        <span class="fw-bold small" style="color:var(--ck-orange)">KES <?= number_format($total_rev, 0) ?> total</span>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:240px;"><canvas id="revChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-calendar3"></i></span> Monthly Revenue</h6>
        <span class="text-muted small">Last 6 months</span>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:240px;"><canvas id="monChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Inventory breakdown ────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-section-title">Inventory Breakdown</span>
</div>

<div class="row g-3 mb-3">

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon orange"><i class="bi bi-trophy-fill"></i></span> Top Products by Stock Value</h6>
      </div>
      <?php if (empty($top_value)): ?>
      <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="ck-empty-title">No data</p></div></div>
      <?php else: $max_val = max(array_column($top_value, 'stock_value')); ?>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th class="ck-td-rank">#</th>
              <th>Product</th>
              <th class="text-end">Qty</th>
              <th class="text-end hide-sm">Cost/Unit</th>
              <th class="text-end">Stock Value</th>
              <th style="width:120px;" class="hide-sm">Share</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($top_value as $i => $r):
            $pct = $max_val > 0 ? round($r['stock_value'] / $max_val * 100) : 0;
            $rc  = $i===0?'r1':($i===1?'r2':($i===2?'r3':''));
          ?>
            <tr>
              <td class="ck-td-rank"><span class="ck-rank <?= $rc ?>"><?= $i+1 ?></span></td>
              <td class="fw-semibold text-truncate" style="max-width:180px;"><?= htmlspecialchars($r['product']) ?></td>
              <td class="text-end"><?= number_format((float)$r['qty']) ?></td>
              <td class="text-end text-muted hide-sm">KES <?= number_format((float)$r['cost_price'], 0) ?></td>
              <td class="text-end fw-bold" style="color:var(--ck-blue)">KES <?= number_format((float)$r['stock_value'], 0) ?></td>
              <td class="hide-sm">
                <div class="ck-prog">
                  <div class="ck-prog-track"><div class="ck-prog-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="ck-prog-pct"><?= $pct ?>%</span>
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

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="card-title"><span class="ck-card-icon orange"><i class="bi bi-pie-chart-fill"></i></span> Stock by Category</h6>
      </div>
      <div class="card-body">
        <?php if (empty($categories)): ?>
        <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-pie-chart"></i></div><p class="ck-empty-title">No data</p></div>
        <?php else: ?>
        <div class="ck-chart" style="height:280px;"><canvas id="catChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Low stock alerts ──────────────────────────────────────── -->
<?php if (!empty($low_stock)): ?>
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-section-title">Low Stock Alerts</span>
  <a href="/low-stock.php" class="btn btn-ck-ghost btn-sm"><i class="bi bi-arrow-right me-1"></i>View all</a>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="card-title"><span class="ck-card-icon amber"><i class="bi bi-exclamation-triangle-fill"></i></span> Items At or Below Alert Quantity</h6>
    <span class="ck-chip ck-chip-amber"><?= count($low_stock) ?> items</span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Product</th>
          <th class="hide-sm">Category</th>
          <th class="text-center">Alert Qty</th>
          <th class="text-center">In Stock</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($low_stock as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td class="hide-sm"><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['category'] ?? '—') ?></span></td>
          <td class="text-center"><?= number_format((float)$r['alert_quantity']) ?></td>
          <td class="text-center fw-bold <?= (float)$r['qty']<=0 ? 'text-danger' : 'text-warning' ?>"><?= number_format((float)$r['qty']) ?></td>
          <td class="text-center">
            <?php if ((float)$r['qty'] <= 0): ?>
              <span class="ck-status out">Out of Stock</span>
            <?php elseif ((float)$r['alert_quantity'] > 0 && (float)$r['qty'] / (float)$r['alert_quantity'] <= 0.5): ?>
              <span class="ck-status critical">Critical</span>
            <?php else: ?>
              <span class="ck-status low">Low</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
