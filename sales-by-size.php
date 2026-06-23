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
    $by_size = ['dates'=>[],'sizes'=>[],'pivot'=>[]];
    $by_cat  = ['dates'=>[],'categories'=>[],'pivot'=>[]];
}

$sl = $by_size['dates']; $sg = $by_size['sizes']; $sp = $by_size['pivot'];
$a  = array_filter($sl, fn($d)=>!empty($sp[$d]));
if(count($a)>0 && count($a)<count($sl)) $sl=array_values($a);

$cl = $by_cat['dates']; $cg = $by_cat['categories']; $cp = $by_cat['pivot'];
$b  = array_filter($cl, fn($d)=>!empty($cp[$d]));
if(count($b)>0 && count($b)<count($cl)) $cl=array_values($b);

$pal=['#1d4ed8','#f97316','#059669','#7c3aed','#d97706','#0284c7','#db2777','#0f766e','#c2410c','#4338ca','#0891b2','#65a30d'];

$sd=[];
foreach($sg as $i=>$sz){
  $data=array_map(fn($d)=>$sp[$d][$sz]??0,$sl);
  $sd[]=['label'=>$sz,'data'=>$data,'backgroundColor'=>$pal[$i%count($pal)],'stack'=>'s'];
}
$cd=[];
foreach($cg as $i=>$cat){
  $data=array_map(fn($d)=>$cp[$d][$cat]??0,$cl);
  $cd[]=['label'=>$cat,'data'=>$data,'backgroundColor'=>$pal[$i%count($pal)],'stack'=>'c'];
}

$total_sold=array_sum(array_map(fn($d)=>array_sum($d),$sp));

$j_sl=json_encode($sl); $j_sd=json_encode($sd); $j_cl=json_encode($cl); $j_cd=json_encode($cd);

$inline_scripts = <<<JS
var shOpts={responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:12}},tooltip:{mode:'index',intersect:false,callbacks:{footer:function(items){return'Total: '+items.reduce(function(s,i){return s+i.parsed.y;},0)+' units';}}}},scales:{x:{stacked:true,grid:{display:false},ticks:{maxTicksLimit:16,maxRotation:45}},y:{stacked:true,beginAtZero:true}}};

(function(){var c=document.getElementById('sizeChart');if(!c)return;new Chart(c,{type:'bar',data:{labels:{$j_sl},datasets:{$j_sd}},options:shOpts});})();
(function(){var c=document.getElementById('catChart');if(!c)return;new Chart(c,{type:'bar',data:{labels:{$j_cl},datasets:{$j_cd}},options:shOpts});})();

document.querySelectorAll('[data-days]').forEach(function(b){b.addEventListener('click',function(){var d=parseInt(this.dataset.days),to=new Date(),fr=new Date();fr.setDate(to.getDate()-(d-1));function f(x){return x.toISOString().split('T')[0];}document.getElementById('inp-from').value=f(fr);document.getElementById('inp-to').value=f(to);document.getElementById('filterForm').submit();});});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
</div>

<div class="ck-pills mb-4">
  <span class="ck-pill"><i class="bi bi-bag-check" style="color:#1d4ed8"></i> Total Sold: <strong><?= number_format($total_sold) ?> units</strong></span>
  <span class="ck-pill"><i class="bi bi-rulers" style="color:#1d4ed8"></i> Sizes: <strong><?= count($sg) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-tag" style="color:#f97316"></i> Categories: <strong><?= count($cg) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-calendar-range text-muted"></i> <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter">
  <div class="ck-date-wrap"><input type="date" id="inp-from" name="from" value="<?= $from ?>"><span class="ck-date-sep">→</span><input type="date" id="inp-to" name="to" value="<?= $to ?>"></div>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets"><button type="button" class="ck-p-btn" data-days="7">7D</button><button type="button" class="ck-p-btn" data-days="30">30D</button><button type="button" class="ck-p-btn" data-days="90">90D</button><button type="button" class="ck-p-btn" data-days="180">6M</button></div>
  <a href="sales-by-size.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<!-- Chart 1 -->
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Daily Sales by Size</span><span class="ck-chip ck-chip-blue"><?= count($sg) ?> sizes</span></div>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-bar-chart-steps"></i></span>Units Sold per Day — by Size</h6>
    <small class="text-muted">Stacked · hover for totals</small>
  </div>
  <div class="card-body">
    <?php if(empty($sg)): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div><p class="mb-0 fw-semibold">No sales data</p></div>
    <?php else: ?>
      <div class="ck-chart" style="height:320px;"><canvas id="sizeChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<?php if(!empty($sg)):
  $st=[];foreach($sg as $sz){$t=0;foreach($sp as $d){$t+=$d[$sz]??0;}$st[$sz]=$t;}arsort($st);$mx=max($st)?:1;
?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-list-ol"></i></span>Total Sold by Size</h6>
    <small class="text-muted"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th style="width:40px;text-align:center;">#</th><th>Size</th><th class="text-end">Units Sold</th><th class="hide-sm" style="width:150px;">Share</th></tr></thead>
      <tbody>
      <?php $rk=1;foreach($st as $sz=>$total):?>
        <tr>
          <td style="text-align:center;"><span class="ck-rank <?= $rk===1?'r1':($rk===2?'r2':($rk===3?'r3':'')) ?>"><?= $rk++ ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($sz) ?></td>
          <td class="text-end fw-bold" style="color:#1d4ed8"><?= number_format($total) ?></td>
          <td class="hide-sm"><div class="ck-prog"><div class="ck-prog-track"><div class="ck-prog-fill" style="width:<?= round($total/$mx*100) ?>%"></div></div><span class="ck-prog-pct"><?= round($total/$mx*100) ?>%</span></div></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php endif;?>

<!-- Chart 2 -->
<div class="d-flex align-items-center justify-content-between mb-3 mt-2"><span class="ck-label">Daily Sales by Category</span><span class="ck-chip ck-chip-orange"><?= count($cg) ?> categories</span></div>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-bar-chart-steps"></i></span>Units Sold per Day — by Category</h6>
    <small class="text-muted">Stacked · hover for totals</small>
  </div>
  <div class="card-body">
    <?php if(empty($cg)): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-tag"></i></div><p class="mb-0 fw-semibold">No category data</p></div>
    <?php else: ?>
      <div class="ck-chart" style="height:320px;"><canvas id="catChart"></canvas></div>
    <?php endif;?>
  </div>
</div>

<?php if(!empty($cg)):
  $ct=[];foreach($cg as $cat){$t=0;foreach($cp as $d){$t+=$d[$cat]??0;}$ct[$cat]=$t;}arsort($ct);$mx2=max($ct)?:1;
?>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-list-ol"></i></span>Total Sold by Category</h6>
    <small class="text-muted"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th style="width:40px;text-align:center;">#</th><th>Category</th><th class="text-end">Units Sold</th><th class="hide-sm" style="width:150px;">Share</th></tr></thead>
      <tbody>
      <?php $rk=1;foreach($ct as $cat=>$total):?>
        <tr>
          <td style="text-align:center;"><span class="ck-rank <?= $rk===1?'r1':($rk===2?'r2':($rk===3?'r3':'')) ?>"><?= $rk++ ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($cat) ?></td>
          <td class="text-end fw-bold" style="color:#f97316"><?= number_format($total) ?></td>
          <td class="hide-sm"><div class="ck-prog"><div class="ck-prog-track"><div class="ck-prog-fill or" style="width:<?= round($total/$mx2*100) ?>%"></div></div><span class="ck-prog-pct"><?= round($total/$mx2*100) ?>%</span></div></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php endif;?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
