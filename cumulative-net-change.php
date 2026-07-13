<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Cumulative Net Change';
$page_subtitle = 'Boys/Girls/Unisex stock quantity over time, anchored to the actual current count';

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$location_id = isset($_GET['loc']) && ctype_digit($_GET['loc']) ? (int)$_GET['loc'] : null;

try {
    $core_movement = get_core_stock_movement($from, $to, $location_id);
    $locations     = get_business_locations();
    $db_error      = null;
} catch (Exception $e) {
    $db_error      = $e->getMessage();
    $core_movement = $locations = [];
}

$current_stock  = !empty($core_movement) ? end($core_movement)['cumulative'] : 0;
$total_received = array_sum(array_column($core_movement, 'received'));
$total_sold     = array_sum(array_column($core_movement, 'sold'));
$location_names = array_column($locations, 'name', 'id');

$j_days  = json_encode(array_column($core_movement, 'day'));
$j_cumul = json_encode(array_column($core_movement, 'cumulative'));
$j_recv  = json_encode(array_column($core_movement, 'received'));
$j_sold  = json_encode(array_column($core_movement, 'sold'));

$inline_scripts = <<<JS
(function(){var c=document.getElementById('cumChart');if(!c||!{$j_days}.length)return;new Chart(c,{type:'line',data:{labels:{$j_days},datasets:[{label:'Stock Qty (Boys/Girls/Unisex)',data:{$j_cumul},borderColor:'#1d4ed8',backgroundColor:'rgba(29,78,216,.1)',fill:true,tension:0.3,pointRadius:{$j_days}.length>60?0:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'},tooltip:{callbacks:{label:function(c){return c.dataset.label+': '+c.parsed.y.toLocaleString()+' units';}}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:16,maxRotation:45}},y:{beginAtZero:false}}}});})();

document.querySelectorAll('[data-days]').forEach(function(b){b.addEventListener('click',function(){var d=parseInt(this.dataset.days),to=new Date(),fr=new Date();fr.setDate(to.getDate()-(d-1));function f(x){return x.toISOString().split('T')[0];}document.getElementById('inp-from').value=f(fr);document.getElementById('inp-to').value=f(to);document.getElementById('filterForm').submit();});});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb mb-0" style="font-size:0.8rem;">
    <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cumulative Net Change</li>
  </ol>
</nav>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
</div>

<div class="ck-pills mb-3">
  <span class="ck-pill"><i class="bi bi-layers" style="color:#1d4ed8"></i> Current Stock: <strong><?= number_format($current_stock) ?> units</strong></span>
  <span class="ck-pill"><i class="bi bi-arrow-down-circle" style="color:#1d4ed8"></i> Received: <strong><?= number_format($total_received) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-arrow-up-circle" style="color:#f97316"></i> Sold: <strong><?= number_format($total_sold) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-calendar-range text-muted"></i> <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter">
  <div class="ck-date-wrap">
    <input type="date" id="inp-from" name="from" value="<?= $from ?>">
    <span class="ck-date-sep">→</span>
    <input type="date" id="inp-to" name="to" value="<?= $to ?>">
  </div>
  <select name="loc" class="form-select form-select-sm w-auto">
    <option value="">All Stock</option>
    <?php foreach ($locations as $loc): ?>
      <option value="<?= $loc['id'] ?>" <?= $location_id === (int)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets">
    <button type="button" class="ck-p-btn" data-days="7">7D</button>
    <button type="button" class="ck-p-btn" data-days="30">30D</button>
    <button type="button" class="ck-p-btn" data-days="90">90D</button>
    <button type="button" class="ck-p-btn" data-days="180">6M</button>
  </div>
  <a href="cumulative-net-change.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-graph-up"></i></span>Stock Quantity Over Time</h6>
    <small class="text-muted">Boys/Girls/Unisex<?= $location_id && isset($location_names[$location_id]) ? ' · ' . htmlspecialchars($location_names[$location_id]) : '' ?></small>
  </div>
  <div class="card-body">
    <?php if (empty($core_movement)): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-graph-up"></i></div><p class="mb-0 fw-semibold">No data</p></div>
    <?php else: ?>
      <p class="text-muted mb-3" style="font-size:0.8rem;">Anchored to today's actual stock quantity (<?= number_format($current_stock) ?> units) — earlier points are reconstructed by working backward from received/sold activity, so they show what stock levels actually were.</p>
      <div class="ck-chart" style="height:400px;"><canvas id="cumChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($core_movement)): ?>
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Day-by-Day Breakdown</span></div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th class="text-end">Received</th>
          <th class="text-end">Sold</th>
          <th class="text-end">Net</th>
          <th class="text-end">Stock Qty</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($core_movement) as $r): ?>
        <tr>
          <td class="text-muted"><?= date('d M Y', strtotime($r['day'])) ?></td>
          <td class="text-end" style="color:#1d4ed8"><?= $r['received'] > 0 ? '+' . number_format($r['received']) : '0' ?></td>
          <td class="text-end" style="color:#f97316"><?= $r['sold'] > 0 ? '-' . number_format($r['sold']) : '0' ?></td>
          <td class="text-end fw-semibold" style="color:<?= $r['net'] >= 0 ? '#059669' : '#dc2626' ?>"><?= ($r['net'] >= 0 ? '+' : '') . number_format($r['net']) ?></td>
          <td class="text-end fw-bold"><?= number_format($r['cumulative']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
