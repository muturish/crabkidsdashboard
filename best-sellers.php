<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Best Sellers';
$page_subtitle = 'Which products sell more — filter by brand, category, sub-category and date';

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$brand_id     = isset($_GET['brand'])   && ctype_digit($_GET['brand'])   ? (int)$_GET['brand'] : null;
$category_id  = isset($_GET['category']) && ctype_digit($_GET['category']) ? (int)$_GET['category'] : null;
$sub_category = isset($_GET['subcat']) && $_GET['subcat'] !== '' ? $_GET['subcat'] : null;

try {
    $opts     = get_filter_options();
    $products = get_best_selling_products($from, $to, $brand_id, $category_id, $sub_category, 100);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $opts     = ['brands' => [], 'categories' => [], 'sub_categories' => []];
    $products = [];
}

$total_qty = array_sum(array_column($products, 'qty_sold'));
$total_rev = array_sum(array_column($products, 'revenue'));
$top10     = array_slice($products, 0, 10);

$j_labels = json_encode(array_map(fn($r) => $r['product'], $top10));
$j_qty    = json_encode(array_map(fn($r) => (float)$r['qty_sold'], $top10));

$inline_scripts = <<<JS
(function(){var c=document.getElementById('bestChart');if(!c||!{$j_labels}.length)return;new Chart(c,{type:'bar',data:{labels:{$j_labels},datasets:[{label:'Units Sold',data:{$j_qty},backgroundColor:'#1d4ed8',borderRadius:4}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,grid:{display:false}},y:{grid:{display:false}}}}});})();

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

<div class="ck-pills mb-3">
  <span class="ck-pill"><i class="bi bi-bag-check" style="color:#1d4ed8"></i> Total Sold: <strong><?= number_format($total_qty) ?> units</strong></span>
  <span class="ck-pill"><i class="bi bi-cash-stack" style="color:#f97316"></i> Revenue: <strong>KES <?= number_format($total_rev) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-box-seam text-muted"></i> Products: <strong><?= count($products) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-calendar-range text-muted"></i> <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter">
  <div class="ck-date-wrap"><input type="date" id="inp-from" name="from" value="<?= $from ?>"><span class="ck-date-sep">→</span><input type="date" id="inp-to" name="to" value="<?= $to ?>"></div>

  <select name="brand" class="form-select form-select-sm w-auto">
    <option value="">All Brands</option>
    <?php foreach ($opts['brands'] as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $brand_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="category" class="form-select form-select-sm w-auto">
    <option value="">All Categories</option>
    <?php foreach ($opts['categories'] as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $category_id === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="subcat" class="form-select form-select-sm w-auto">
    <option value="">All Sizes</option>
    <?php foreach ($opts['sub_categories'] as $sc): ?>
      <option value="<?= htmlspecialchars($sc) ?>" <?= $sub_category === $sc ? 'selected' : '' ?>><?= htmlspecialchars($sc) ?></option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets"><button type="button" class="ck-p-btn" data-days="7">7D</button><button type="button" class="ck-p-btn" data-days="30">30D</button><button type="button" class="ck-p-btn" data-days="90">90D</button><button type="button" class="ck-p-btn" data-days="180">6M</button></div>
  <a href="best-sellers.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Top 10 Products</span><span class="ck-chip ck-chip-blue">by units sold</span></div>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-trophy-fill"></i></span>Best-Selling Products</h6>
    <small class="text-muted"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></small>
  </div>
  <div class="card-body">
    <?php if (empty($top10)): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div><p class="mb-0 fw-semibold">No sales data for this filter</p></div>
    <?php else: ?>
      <div class="ck-chart" style="height:<?= max(220, count($top10) * 34) ?>px;"><canvas id="bestChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 mt-2"><span class="ck-label">Full Ranking</span><span class="ck-chip ck-chip-orange"><?= count($products) ?> products</span></div>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-list-ol"></i></span>Products Ranked by Units Sold</h6>
    <small class="text-muted">Filtered by brand / category / size above</small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th style="width:40px;text-align:center;">#</th>
          <th>Product</th>
          <th class="hide-sm">Brand</th>
          <th class="hide-sm">Category</th>
          <th class="hide-sm">Size</th>
          <th class="text-end">Units Sold</th>
          <th class="text-end hide-sm">Revenue</th>
          <th class="hide-sm" style="width:120px;">Share</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($products)): ?>
        <tr><td colspan="8"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="mb-0 fw-semibold">No products sold in this range</p></div></td></tr>
      <?php else:
        $mx = max(array_column($products, 'qty_sold')) ?: 1;
        foreach ($products as $i => $r):
          $pct = round((float)$r['qty_sold'] / $mx * 100);
          $rc  = $i===0?'r1':($i===1?'r2':($i===2?'r3':''));
      ?>
        <tr>
          <td style="text-align:center;"><span class="ck-rank <?= $rc ?>"><?= $i+1 ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td class="hide-sm"><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['brand'] ?? '—') ?></span></td>
          <td class="hide-sm"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
          <td class="hide-sm"><?= htmlspecialchars($r['sub_category'] ?? '—') ?></td>
          <td class="text-end fw-bold" style="color:#1d4ed8"><?= number_format((float)$r['qty_sold']) ?></td>
          <td class="text-end hide-sm">KES <?= number_format((float)$r['revenue']) ?></td>
          <td class="hide-sm"><div class="ck-prog"><div class="ck-prog-track"><div class="ck-prog-fill" style="width:<?= $pct ?>%"></div></div><span class="ck-prog-pct"><?= $pct ?>%</span></div></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
