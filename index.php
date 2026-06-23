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

$j_days  = json_encode(array_column($movement, 'day'));
$j_recv  = json_encode(array_column($movement, 'received'));
$j_sold  = json_encode(array_column($movement, 'sold'));
$j_cumul = json_encode(array_column($movement, 'cumulative'));
$j_rdays = json_encode(array_column($revenue,  'day'));
$j_rev   = json_encode(array_column($revenue,  'revenue'));
$j_clbl  = json_encode(array_column($categories,'category'));
$j_cval  = json_encode(array_column($categories,'total_qty'));
$j_mlbl  = json_encode(array_column($monthly,  'month'));
$j_mrev  = json_encode(array_column($monthly,  'revenue'));

$inline_scripts = <<<JS
var PAL=['#1d4ed8','#f97316','#059669','#0284c7','#7c3aed','#d97706','#db2777','#0f766e','#c2410c','#4338ca'];

(function(){var c=document.getElementById('movChart');if(!c)return;new Chart(c,{type:'bar',data:{labels:{$j_days},datasets:[{label:'Received',data:{$j_recv},backgroundColor:'rgba(29,78,216,.75)'},{label:'Sold',data:{$j_sold},backgroundColor:'rgba(249,115,22,.75)'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{beginAtZero:true}}}});})();

(function(){var c=document.getElementById('cumChart');if(!c)return;new Chart(c,{type:'line',data:{labels:{$j_days},datasets:[{label:'Net Change',data:{$j_cumul},borderColor:'#1d4ed8',backgroundColor:'rgba(29,78,216,.08)',fill:true,pointRadius:{$j_days}.length>60?0:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{}}}});})();

(function(){var c=document.getElementById('revChart');if(!c)return;new Chart(c,{type:'line',data:{labels:{$j_rdays},datasets:[{label:'Revenue (KES)',data:{$j_rev},borderColor:'#f97316',backgroundColor:'rgba(249,115,22,.1)',fill:true,pointRadius:{$j_rdays}.length>60?0:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'},tooltip:{callbacks:{label:function(c){return'KES '+c.parsed.y.toLocaleString();}}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:14}},y:{beginAtZero:true,ticks:{callback:function(v){return'KES '+(v/1000).toFixed(0)+'k';}}}}}});})();

(function(){var c=document.getElementById('catChart');if(!c||!{$j_clbl}.length)return;new Chart(c,{type:'doughnut',data:{labels:{$j_clbl},datasets:[{data:{$j_cval},backgroundColor:PAL,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom'}}}});})();

(function(){var c=document.getElementById('monChart');if(!c||!{$j_mlbl}.length)return;new Chart(c,{type:'bar',data:{labels:{$j_mlbl},datasets:[{label:'Revenue (KES)',data:{$j_mrev},backgroundColor:'rgba(29,78,216,.75)',borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{callback:function(v){return'KES '+(v/1000).toFixed(0)+'k';}}}}}});})();

document.querySelectorAll('[data-days]').forEach(function(b){b.addEventListener('click',function(){var d=parseInt(this.dataset.days),to=new Date(),fr=new Date();fr.setDate(to.getDate()-(d-1));function f(x){return x.toISOString().split('T')[0];}document.getElementById('inp-from').value=f(fr);document.getElementById('inp-to').value=f(to);document.getElementById('filterForm').submit();});});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i> <div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<!-- Page header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1>
    <p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p>
  </div>
</div>

<!-- KPI row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-blue">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Active Products</p><span class="ck-kpi-icon"><i class="bi bi-box-seam"></i></span></div>
      <div class="ck-kpi-val"><?= number_format($kpis['total_products']) ?></div>
      <div class="ck-kpi-sub">total SKUs</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-sky">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Units in Stock</p><span class="ck-kpi-icon"><i class="bi bi-layers"></i></span></div>
      <div class="ck-kpi-val"><?= number_format($kpis['total_units']) ?></div>
      <div class="ck-kpi-sub">all locations</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-slate">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Cost Value</p><span class="ck-kpi-icon"><i class="bi bi-wallet2"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= number_format($kpis['stock_cost_value'], 0) ?></div>
      <div class="ck-kpi-sub">at purchase price</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-orange">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Sell Value</p><span class="ck-kpi-icon"><i class="bi bi-currency-dollar"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= number_format($kpis['stock_sell_value'], 0) ?></div>
      <div class="ck-kpi-sub">at selling price</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-green">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Today's Sales</p><span class="ck-kpi-icon"><i class="bi bi-receipt"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= number_format($kpis['today_sales'], 0) ?></div>
      <div class="ck-kpi-sub"><?= date('d M Y') ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="ck-kpi kpi-red">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Alerts</p><span class="ck-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div>
      <div class="ck-kpi-val"><?= $kpis['low_stock'] ?><small class="text-muted fw-normal ms-1" style="font-size:.75rem;">low</small></div>
      <div class="ck-kpi-sub"><?= $kpis['out_of_stock'] ?> out &nbsp;<a href="/low-stock.php" class="text-danger text-decoration-none fw-semibold">View →</a></div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
  <span class="ck-label">Stock Movement</span>
  <div class="ck-pills">
    <span class="ck-pill"><i class="bi bi-arrow-down-circle" style="color:#1d4ed8"></i> Recv: <strong><?= number_format($total_recv) ?></strong></span>
    <span class="ck-pill"><i class="bi bi-arrow-up-circle" style="color:#f97316"></i> Sold: <strong><?= number_format($total_sold) ?></strong></span>
    <span class="ck-pill" style="color:<?= $net_change>=0?'#059669':'#dc2626' ?>">Net: <strong><?= ($net_change>=0?'+':'').number_format($net_change) ?></strong></span>
  </div>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter">
  <div class="ck-date-wrap">
    <input type="date" id="inp-from" name="from" value="<?= $from ?>">
    <span class="ck-date-sep">→</span>
    <input type="date" id="inp-to" name="to" value="<?= $to ?>">
  </div>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets">
    <button type="button" class="ck-p-btn" data-days="7">7D</button>
    <button type="button" class="ck-p-btn" data-days="30">30D</button>
    <button type="button" class="ck-p-btn" data-days="90">90D</button>
    <button type="button" class="ck-p-btn" data-days="180">6M</button>
  </div>
</form>

<!-- Charts row 1 -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-bar-chart-fill"></i></span>Daily Units — Received vs Sold</h6>
        <small class="text-muted"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></small>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:255px;"><canvas id="movChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-graph-up"></i></span>Cumulative Net Change</h6>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:255px;"><canvas id="cumChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Charts row 2 -->
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-graph-up-arrow"></i></span>Daily Sales Revenue</h6>
        <small class="fw-bold" style="color:#f97316">KES <?= number_format($total_rev, 0) ?></small>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:235px;"><canvas id="revChart"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-calendar3"></i></span>Monthly Revenue</h6>
        <small class="text-muted">6 months</small>
      </div>
      <div class="card-body">
        <div class="ck-chart" style="height:235px;"><canvas id="monChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Inventory section -->
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-label">Inventory Breakdown</span>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-trophy-fill"></i></span>Top Products by Stock Value</h6>
      </div>
      <?php if (empty($top_value)): ?>
        <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="mb-0 fw-semibold">No data</p></div></div>
      <?php else: $mx = max(array_column($top_value,'stock_value')); ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th style="width:40px;text-align:center;">#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end hide-sm">Cost/Unit</th><th class="text-end">Stock Value</th><th class="hide-sm" style="width:120px;">Share</th></tr></thead>
            <tbody>
            <?php foreach ($top_value as $i => $r):
              $pct = $mx > 0 ? round($r['stock_value']/$mx*100) : 0;
              $rc  = $i===0?'r1':($i===1?'r2':($i===2?'r3':''));
            ?>
              <tr>
                <td style="text-align:center;"><span class="ck-rank <?= $rc ?>"><?= $i+1 ?></span></td>
                <td class="fw-semibold text-truncate" style="max-width:180px;"><?= htmlspecialchars($r['product']) ?></td>
                <td class="text-end"><?= number_format((float)$r['qty']) ?></td>
                <td class="text-end text-muted hide-sm">KES <?= number_format((float)$r['cost_price'],0) ?></td>
                <td class="text-end fw-bold" style="color:#1d4ed8">KES <?= number_format((float)$r['stock_value'],0) ?></td>
                <td class="hide-sm"><div class="ck-prog"><div class="ck-prog-track"><div class="ck-prog-fill" style="width:<?= $pct ?>%"></div></div><span class="ck-prog-pct"><?= $pct ?>%</span></div></td>
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
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-pie-chart-fill"></i></span>Stock by Category</h6>
      </div>
      <div class="card-body">
        <?php if (empty($categories)): ?>
          <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-pie-chart"></i></div><p class="mb-0 fw-semibold">No data</p></div>
        <?php else: ?>
          <div class="ck-chart" style="height:275px;"><canvas id="catChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Low stock alerts -->
<?php if (!empty($low_stock)): ?>
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-label">Low Stock Alerts</span>
  <a href="/low-stock.php" class="btn btn-ck-ghost btn-sm"><i class="bi bi-arrow-right me-1"></i>View all</a>
</div>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-amber"><i class="bi bi-exclamation-triangle-fill"></i></span>Items At or Below Alert Quantity</h6>
    <span class="ck-chip ck-chip-amber"><?= count($low_stock) ?> items</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Product</th><th class="hide-sm">Category</th><th class="text-center">Alert Qty</th><th class="text-center">In Stock</th><th class="text-center">Status</th></tr></thead>
      <tbody>
      <?php foreach ($low_stock as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td class="hide-sm"><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['category'] ?? '—') ?></span></td>
          <td class="text-center"><?= number_format((float)$r['alert_quantity']) ?></td>
          <td class="text-center fw-bold <?= (float)$r['qty']<=0?'text-danger':'text-warning' ?>"><?= number_format((float)$r['qty']) ?></td>
          <td class="text-center">
            <?php if ((float)$r['qty']<=0): ?><span class="ck-status out">Out of Stock</span>
            <?php elseif ((float)$r['alert_quantity']>0 && (float)$r['qty']/(float)$r['alert_quantity']<=0.5): ?><span class="ck-status critical">Critical</span>
            <?php else: ?><span class="ck-status low">Low</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
