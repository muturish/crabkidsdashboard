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

// ── Build Chart.js datasets for Size chart ────────────────────
$size_labels  = $by_size['dates'];
$size_groups  = $by_size['sizes'];
$size_pivot   = $by_size['pivot'];

// For readability, only show dates that have at least 1 sale
$active_dates_size = array_filter($size_labels, fn($d) => !empty($size_pivot[$d]));
if (count($active_dates_size) < count($size_labels) && count($active_dates_size) > 0) {
    $size_labels = array_values($active_dates_size);
}

$size_datasets = [];
$palette = ['#1d4ed8','#f97316','#059669','#7c3aed','#d97706','#0284c7','#db2777','#0f766e','#c2410c','#4338ca','#0891b2','#65a30d','#9333ea','#e11d48','#b45309'];
foreach ($size_groups as $i => $sz) {
    $data = [];
    foreach ($size_labels as $d) {
        $data[] = $size_pivot[$d][$sz] ?? 0;
    }
    $color = $palette[$i % count($palette)];
    $size_datasets[] = [
        'label'           => $sz,
        'data'            => $data,
        'backgroundColor' => $color,
        'stack'           => 'size',
    ];
}

// ── Build Chart.js datasets for Category chart ────────────────
$cat_labels   = $by_cat['dates'];
$cat_groups   = $by_cat['categories'];
$cat_pivot    = $by_cat['pivot'];

$active_dates_cat = array_filter($cat_labels, fn($d) => !empty($cat_pivot[$d]));
if (count($active_dates_cat) < count($cat_labels) && count($active_dates_cat) > 0) {
    $cat_labels = array_values($active_dates_cat);
}

$cat_datasets = [];
foreach ($cat_groups as $i => $cat) {
    $data = [];
    foreach ($cat_labels as $d) {
        $data[] = $cat_pivot[$d][$cat] ?? 0;
    }
    $color = $palette[$i % count($palette)];
    $cat_datasets[] = [
        'label'           => $cat,
        'data'            => $data,
        'backgroundColor' => $color,
        'stack'           => 'cat',
    ];
}

// Totals for summary pills
$total_size_sold = 0;
foreach ($size_pivot as $day_data) {
    $total_size_sold += array_sum($day_data);
}

$j_size_labels   = json_encode($size_labels);
$j_size_datasets = json_encode($size_datasets);
$j_cat_labels    = json_encode($cat_labels);
$j_cat_datasets  = json_encode($cat_datasets);

$inline_scripts = <<<JS
// ── 1. Sales by Size (stacked bar) ──────────────────────────
(function(){
  var ctx = document.getElementById('sizeChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels: {$j_size_labels}, datasets: {$j_size_datasets} },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { padding: 12, boxWidth: 10 } },
        tooltip: {
          mode: 'index', intersect: false,
          callbacks: {
            footer: function(items) {
              var total = items.reduce(function(s, i){ return s + i.parsed.y; }, 0);
              return 'Total: ' + total + ' units';
            }
          }
        }
      },
      scales: {
        x: { stacked: true, grid: { display: false }, ticks: { maxTicksLimit: 16, maxRotation: 45 } },
        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });
})();

// ── 2. Sales by Category (stacked bar) ──────────────────────
(function(){
  var ctx = document.getElementById('catChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels: {$j_cat_labels}, datasets: {$j_cat_datasets} },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { padding: 12, boxWidth: 10 } },
        tooltip: {
          mode: 'index', intersect: false,
          callbacks: {
            footer: function(items) {
              var total = items.reduce(function(s, i){ return s + i.parsed.y; }, 0);
              return 'Total: ' + total + ' units';
            }
          }
        }
      },
      scales: {
        x: { stacked: true, grid: { display: false }, ticks: { maxTicksLimit: 16, maxRotation: 45 } },
        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });
})();

// Preset date buttons
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

<!-- ── Page header ──────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></div>
  </div>
</div>

<!-- ── Summary pills ───────────────────────────────────────── -->
<div class="stat-pills" style="margin-bottom:20px;">
  <span class="stat-pill">
    <i class="bi bi-bag-check text-blue"></i>
    Total Sold: <span class="pill-value"><?= number_format($total_size_sold) ?> units</span>
  </span>
  <span class="stat-pill">
    <i class="bi bi-rulers text-orange"></i>
    Size Groups: <span class="pill-value"><?= count($size_groups) ?></span>
  </span>
  <span class="stat-pill">
    <i class="bi bi-tag text-blue"></i>
    Categories: <span class="pill-value"><?= count($cat_groups) ?></span>
  </span>
  <span class="stat-pill">
    <i class="bi bi-calendar-range text-secondary"></i>
    <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?>
  </span>
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
  <a href="sales-by-size.php" class="btn btn-ghost btn-sm">Reset</a>
</form>

<!-- ── Chart 1: By Size ──────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Daily Sales by Size</span>
  <span class="chip chip-blue"><?= count($size_groups) ?> sizes</span>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h6 class="card-title">
      <span class="card-title-icon blue"><i class="bi bi-bar-chart-steps"></i></span>
      Units Sold per Day — by Size Group
    </h6>
    <span class="card-meta">Stacked · hover for totals</span>
  </div>
  <div class="card-body">
    <?php if (empty($size_groups)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-bar-chart"></i></div>
      <div class="empty-state-title">No sales data</div>
      <div class="empty-state-desc">No sales were recorded in this date range.</div>
    </div>
    <?php else: ?>
    <div class="chart-container" style="height:340px;">
      <canvas id="sizeChart"></canvas>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Size totals table ─────────────────────────────────────── -->
<?php if (!empty($size_groups)):
  // Compute total per size across the period
  $size_totals = [];
  foreach ($size_groups as $sz) {
      $total = 0;
      foreach ($size_pivot as $day_data) {
          $total += $day_data[$sz] ?? 0;
      }
      $size_totals[$sz] = $total;
  }
  arsort($size_totals);
  $max_size_total = max($size_totals) ?: 1;
?>
<div class="card" style="margin-bottom:28px;">
  <div class="card-header">
    <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-list-ol"></i></span> Total Sold by Size</h6>
    <span class="card-meta"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
  </div>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th class="td-rank">#</th>
          <th>Size</th>
          <th style="text-align:right;">Units Sold</th>
          <th style="width:160px;">Share</th>
        </tr>
      </thead>
      <tbody>
      <?php $rank = 1; foreach ($size_totals as $sz => $total): ?>
        <tr>
          <td class="td-rank"><span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'')) ?>"><?= $rank++ ?></span></td>
          <td class="fw-600"><?= htmlspecialchars($sz) ?></td>
          <td style="text-align:right;" class="fw-700 text-blue"><?= number_format($total) ?></td>
          <td>
            <div class="progress-bar-wrap">
              <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width:<?= round($total/$max_size_total*100) ?>%"></div>
              </div>
              <span class="progress-bar-label"><?= round($total/$max_size_total*100) ?>%</span>
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
<div class="section-header">
  <span class="section-title">Daily Sales by Category</span>
  <span class="chip chip-orange"><?= count($cat_groups) ?> categories</span>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h6 class="card-title">
      <span class="card-title-icon orange"><i class="bi bi-bar-chart-steps"></i></span>
      Units Sold per Day — by Category
    </h6>
    <span class="card-meta">Stacked · hover for totals</span>
  </div>
  <div class="card-body">
    <?php if (empty($cat_groups)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-tag"></i></div>
      <div class="empty-state-title">No category data</div>
      <div class="empty-state-desc">No categorised sales found in this period.</div>
    </div>
    <?php else: ?>
    <div class="chart-container" style="height:340px;">
      <canvas id="catChart"></canvas>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Category totals table ─────────────────────────────────── -->
<?php if (!empty($cat_groups)):
  $cat_totals = [];
  foreach ($cat_groups as $cat) {
      $total = 0;
      foreach ($cat_pivot as $day_data) {
          $total += $day_data[$cat] ?? 0;
      }
      $cat_totals[$cat] = $total;
  }
  arsort($cat_totals);
  $max_cat_total = max($cat_totals) ?: 1;
?>
<div class="card">
  <div class="card-header">
    <h6 class="card-title"><span class="card-title-icon orange"><i class="bi bi-list-ol"></i></span> Total Sold by Category</h6>
    <span class="card-meta"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
  </div>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th class="td-rank">#</th>
          <th>Category</th>
          <th style="text-align:right;">Units Sold</th>
          <th style="width:160px;">Share</th>
        </tr>
      </thead>
      <tbody>
      <?php $rank = 1; foreach ($cat_totals as $cat => $total): ?>
        <tr>
          <td class="td-rank"><span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'')) ?>"><?= $rank++ ?></span></td>
          <td class="fw-600"><?= htmlspecialchars($cat) ?></td>
          <td style="text-align:right;" class="fw-700 text-orange"><?= number_format($total) ?></td>
          <td>
            <div class="progress-bar-wrap">
              <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width:<?= round($total/$max_cat_total*100) ?>%;background:linear-gradient(90deg,var(--brand-orange-light),var(--brand-orange));"></div>
              </div>
              <span class="progress-bar-label"><?= round($total/$max_cat_total*100) ?>%</span>
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
