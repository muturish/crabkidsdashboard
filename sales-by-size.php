<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Sales by Size & Category';
$page_subtitle = 'Daily units sold broken down by size and by product category';

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

try {
    $by_size = get_daily_sold_by_size($from, $to);
    $by_cat  = get_daily_sold_by_category($from, $to);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $by_size = ['dates' => [], 'sizes' => [], 'pivot' => []];
    $by_cat  = ['dates' => [], 'categories' => [], 'pivot' => []];
}

$size_labels = $by_size['dates'];
$size_groups = $by_size['sizes'];
$size_pivot  = $by_size['pivot'];

// Filter to active dates only
$active = array_filter($size_labels, fn($d) => !empty($size_pivot[$d]));
if (count($active) > 0 && count($active) < count($size_labels)) {
    $size_labels = array_values($active);
}

$palette = ['#1d4ed8','#f97316','#059669','#7c3aed','#d97706','#0284c7','#db2777','#0f766e','#c2410c','#4338ca','#0891b2','#65a30d'];

$size_datasets = [];
foreach ($size_groups as $i => $sz) {
    $data = [];
    foreach ($size_labels as $d) { $data[] = $size_pivot[$d][$sz] ?? 0; }
    $size_datasets[] = ['label' => $sz, 'data' => $data, 'backgroundColor' => $palette[$i % count($palette)], 'stack' => 'sz'];
}

$cat_labels = $by_cat['dates'];
$cat_groups = $by_cat['categories'];
$cat_pivot  = $by_cat['pivot'];

$active2 = array_filter($cat_labels, fn($d) => !empty($cat_pivot[$d]));
if (count($active2) > 0 && count($active2) < count($cat_labels)) {
    $cat_labels = array_values($active2);
}

$cat_datasets = [];
foreach ($cat_groups as $i => $cat) {
    $data = [];
    foreach ($cat_labels as $d) { $data[] = $cat_pivot[$d][$cat] ?? 0; }
    $cat_datasets[] = ['label' => $cat, 'data' => $data, 'backgroundColor' => $palette[$i % count($palette)], 'stack' => 'cat'];
}

$total_sold = 0;
foreach ($size_pivot as $day_data) { $total_sold += array_sum($day_data); }

$j_sl  = json_encode($size_labels);
$j_sd  = json_encode($size_datasets);
$j_cl  = json_encode($cat_labels);
$j_cd  = json_encode($cat_datasets);

$inline_scripts = <<<JS
var opts = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{position:'bottom',labels:{padding:14}}, tooltip:{mode:'index',intersect:false,callbacks:{footer:function(items){var t=items.reduce(function(s,i){return s+i.parsed.y;},0);return 'Total: '+t+' units';}}} },
  scales:{ x:{stacked:true,grid:{display:false},ticks:{maxTicksLimit:16,maxRotation:45}}, y:{stacked:true,beginAtZero:true,ticks:{stepSize:1}} }
};

(function(){
  var ctx = document.getElementById('sizeChart');
  if (!ctx) return;
  new Chart(ctx, { type:'bar', data:{ labels:{$j_sl}, datasets:{$j_sd} }, options:opts });
})();

(function(){
  var ctx = document.getElementById('catChart');
  if (!ctx) return;
  new Chart(ctx, { type:'bar', data:{ labels:{$j_cl}, datasets:{$j_cd} }, options:opts });
})();

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

<!-- ── Summary pills ─────────────────────────────────────────── -->
<div class="ck-pills mb-4">
  <span class="ck-pill"><i class="bi bi-bag-check text-primary"></i> Total Sold: <strong><?= number_format($total_sold) ?> units</strong></span>
  <span class="ck-pill"><i class="bi bi-rulers" style="color:var(--ck-blue)"></i> Sizes: <strong><?= count($size_groups) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-tag" style="color:var(--ck-orange)"></i> Categories: <strong><?= count($cat_groups) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-calendar-range text-muted"></i> <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
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
  <a href="sales-by-size.php" class="btn btn-ck-ghost btn-sm ms-1">Reset</a>
</form>

<!-- ── Chart 1: By Size ──────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <span class="ck-section-title">Daily Sales by Size</span>
  <span class="ck-chip ck-chip-blue"><?= count($size_groups) ?> sizes</span>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-bar-chart-steps"></i></span> Units Sold per Day — by Size Group</h6>
    <span class="text-muted small">Stacked · hover for totals</span>
  </div>
  <div class="card-body">
    <?php if (empty($size_groups)): ?>
    <div class="ck-empty">
      <div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div>
      <p class="ck-empty-title">No sales data</p>
      <p class="ck-empty-desc">No sales were recorded in this date range.</p>
    </div>
    <?php else: ?>
    <div class="ck-chart" style="height:340px;"><canvas id="sizeChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Size totals table ─────────────────────────────────────── -->
<?php if (!empty($size_groups)):
  $size_totals = [];
  foreach ($size_groups as $sz) {
      $t = 0;
      foreach ($size_pivot as $day_data) { $t += $day_data[$sz] ?? 0; }
      $size_totals[$sz] = $t;
  }
  arsort($size_totals);
  $mx = max($size_totals) ?: 1;
?>
<div class="card mb-4">
  <div class="card-header">
    <h6 class="card-title"><span class="ck-card-icon blue"><i class="bi bi-list-ol"></i></span> Total Sold by Size</h6>
    <span class="text-muted small"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead><tr><th class="ck-td-rank">#</th><th>Size</th><th class="text-end">Units Sold</th><th style="width:160px;" class="hide-sm">Share</th></tr></thead>
      <tbody>
      <?php $rk=1; foreach ($size_totals as $sz => $total): ?>
        <tr>
          <td class="ck-td-rank"><span class="ck-rank <?= $rk===1?'r1':($rk===2?'r2':($rk===3?'r3':'')) ?>"><?= $rk++ ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($sz) ?></td>
          <td class="text-end fw-bold" style="color:var(--ck-blue)"><?= number_format($total) ?></td>
          <td class="hide-sm">
            <div class="ck-prog">
              <div class="ck-prog-track"><div class="ck-prog-fill" style="width:<?= round($total/$mx*100) ?>%"></div></div>
              <span class="ck-prog-pct"><?= round($total/$mx*100) ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Chart 2: By Category ──────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <span class="ck-section-title">Daily Sales by Category</span>
  <span class="ck-chip ck-chip-orange"><?= count($cat_groups) ?> categories</span>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="card-title"><span class="ck-card-icon orange"><i class="bi bi-bar-chart-steps"></i></span> Units Sold per Day — by Category</h6>
    <span class="text-muted small">Stacked · hover for totals</span>
  </div>
  <div class="card-body">
    <?php if (empty($cat_groups)): ?>
    <div class="ck-empty">
      <div class="ck-empty-icon"><i class="bi bi-tag"></i></div>
      <p class="ck-empty-title">No category data</p>
      <p class="ck-empty-desc">No categorised sales found in this period.</p>
    </div>
    <?php else: ?>
    <div class="ck-chart" style="height:340px;"><canvas id="catChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Category totals table ─────────────────────────────────── -->
<?php if (!empty($cat_groups)):
  $cat_totals = [];
  foreach ($cat_groups as $cat) {
      $t = 0;
      foreach ($cat_pivot as $day_data) { $t += $day_data[$cat] ?? 0; }
      $cat_totals[$cat] = $t;
  }
  arsort($cat_totals);
  $mx2 = max($cat_totals) ?: 1;
?>
<div class="card">
  <div class="card-header">
    <h6 class="card-title"><span class="ck-card-icon orange"><i class="bi bi-list-ol"></i></span> Total Sold by Category</h6>
    <span class="text-muted small"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead><tr><th class="ck-td-rank">#</th><th>Category</th><th class="text-end">Units Sold</th><th style="width:160px;" class="hide-sm">Share</th></tr></thead>
      <tbody>
      <?php $rk=1; foreach ($cat_totals as $cat => $total): ?>
        <tr>
          <td class="ck-td-rank"><span class="ck-rank <?= $rk===1?'r1':($rk===2?'r2':($rk===3?'r3':'')) ?>"><?= $rk++ ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($cat) ?></td>
          <td class="text-end fw-bold" style="color:var(--ck-orange)"><?= number_format($total) ?></td>
          <td class="hide-sm">
            <div class="ck-prog">
              <div class="ck-prog-track"><div class="ck-prog-fill orange" style="width:<?= round($total/$mx2*100) ?>%"></div></div>
              <span class="ck-prog-pct"><?= round($total/$mx2*100) ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
