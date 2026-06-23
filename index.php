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

$j_days   = json_encode(array_column($movement,   'day'));
$j_recv   = json_encode(array_column($movement,   'received'));
$j_sold   = json_encode(array_column($movement,   'sold'));
$j_cumul  = json_encode(array_column($movement,   'cumulative'));
$j_rdays  = json_encode(array_column($revenue,    'day'));
$j_rev    = json_encode(array_column($revenue,    'revenue'));
$j_clbl   = json_encode(array_column($categories, 'category'));
$j_cval   = json_encode(array_column($categories, 'total_qty'));
$j_mlbl   = json_encode(array_column($monthly,    'month'));
$j_mrev   = json_encode(array_column($monthly,    'revenue'));

$inline_scripts = <<<JS
const PALETTE = ['#1d4ed8','#f97316','#059669','#0284c7','#7c3aed','#d97706','#db2777','#0f766e','#c2410c','#4338ca'];

function maxTick(n) { return { maxTicksLimit: n }; }

// 1. Stock movement
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
      scales: {
        x: { grid: { display: false }, ticks: maxTick(14) },
        y: { beginAtZero: true }
      }
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
      datasets: [{
        label: 'Net Stock Change',
        data: {$j_cumul},
        borderColor: '#1d4ed8',
        backgroundColor: 'rgba(29,78,216,.08)',
        fill: true,
        pointRadius: {$j_days}.length > 60 ? 0 : 3
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { grid: { display: false }, ticks: maxTick(14) },
        y: {}
      }
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
      datasets: [{
        label: 'Revenue (KES)',
        data: {$j_rev},
        borderColor: '#f97316',
        backgroundColor: 'rgba(249,115,22,.1)',
        fill: true,
        pointRadius: {$j_rdays}.length > 60 ? 0 : 3
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top' },
        tooltip: { callbacks: { label: function(c){ return 'KES ' + c.parsed.y.toLocaleString(); } } }
      },
      scales: {
        x: { grid: { display: false }, ticks: maxTick(14) },
        y: { beginAtZero: true, ticks: { callback: function(v){ return 'KES ' + (v/1000).toFixed(0) + 'k'; } } }
      }
    }
  });
})();

// 4. Category donut
(function(){
  var ctx = document.getElementById('catChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: {$j_clbl},
      datasets: [{ data: {$j_cval}, backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '62%',
      plugins: { legend: { position: 'bottom', labels: { padding: 14 } } }
    }
  });
})();

// 5. Monthly bar
(function(){
  var ctx = document.getElementById('monChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: {$j_mlbl},
      datasets: [{
        label: 'Revenue (KES)',
        data: {$j_mrev},
        backgroundColor: 'rgba(29,78,216,.75)',
        borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { callback: function(v){ return 'KES '+(v/1000).toFixed(0)+'k'; } } }
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

<!-- ── KPI Grid ───────────────────────────────────────────────── -->
<div class="kpi-grid">

  <div class="kpi-card kpi-blue">
    <div class="kpi-card-top">
      <span class="kpi-label">Active Products</span>
      <span class="kpi-icon"><i class="bi bi-box-seam"></i></span>
    </div>
    <div class="kpi-value"><?= number_format($kpis['total_products']) ?></div>
    <div class="kpi-sub">total SKUs</div>
  </div>

  <div class="kpi-card kpi-sky">
    <div class="kpi-card-top">
      <span class="kpi-label">Units in Stock</span>
      <span class="kpi-icon"><i class="bi bi-layers"></i></span>
    </div>
    <div class="kpi-value"><?= number_format($kpis['total_units']) ?></div>
    <div class="kpi-sub">all locations</div>
  </div>

  <div class="kpi-card kpi-slate">
    <div class="kpi-card-top">
      <span class="kpi-label">Cost Value</span>
      <span class="kpi-icon"><i class="bi bi-wallet2"></i></span>
    </div>
    <div class="kpi-value kpi-value-sm">KES <?= number_format($kpis['stock_cost_value'], 0) ?></div>
    <div class="kpi-sub">at purchase price</div>
  </div>

  <div class="kpi-card kpi-orange">
    <div class="kpi-card-top">
      <span class="kpi-label">Sell Value</span>
      <span class="kpi-icon"><i class="bi bi-currency-dollar"></i></span>
    </div>
    <div class="kpi-value kpi-value-sm">KES <?= number_format($kpis['stock_sell_value'], 0) ?></div>
    <div class="kpi-sub">at selling price</div>
  </div>

  <div class="kpi-card kpi-green">
    <div class="kpi-card-top">
      <span class="kpi-label">Today's Sales</span>
      <span class="kpi-icon"><i class="bi bi-receipt"></i></span>
    </div>
    <div class="kpi-value kpi-value-sm">KES <?= number_format($kpis['today_sales'], 0) ?></div>
    <div class="kpi-sub"><?= date('d M Y') ?></div>
  </div>

  <div class="kpi-card kpi-red">
    <div class="kpi-card-top">
      <span class="kpi-label">Alerts</span>
      <span class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
    </div>
    <div class="kpi-value"><?= $kpis['low_stock'] ?><span style="font-size:14px;font-weight:500;color:var(--text-tertiary);margin-left:4px;">low</span></div>
    <div class="kpi-sub"><?= $kpis['out_of_stock'] ?> out of stock &nbsp;<a href="/low-stock.php" style="color:var(--color-danger);text-decoration:none;font-weight:600;">View →</a></div>
  </div>

</div>

<!-- ── Filter toolbar ─────────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Stock Movement</span>
  <div class="stat-pills">
    <span class="stat-pill"><i class="bi bi-arrow-down-circle text-blue"></i> Received: <span class="pill-value"><?= number_format($total_recv) ?></span></span>
    <span class="stat-pill"><i class="bi bi-arrow-up-circle text-orange"></i> Sold: <span class="pill-value"><?= number_format($total_sold) ?></span></span>
    <span class="stat-pill" style="color:<?= $net_change >= 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>">
      Net: <span class="pill-value"><?= ($net_change >= 0 ? '+' : '') . number_format($net_change) ?></span>
    </span>
  </div>
</div>

<form id="filterForm" method="GET" action="" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
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
</form>

<!-- ── Charts row 1 ──────────────────────────────────────────── -->
<div class="grid grid-12-8" style="margin-bottom:16px;">

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-bar-chart-fill"></i></span> Daily Units — Received vs Sold</h6>
      <span class="card-meta"><?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;"><canvas id="movChart"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-graph-up"></i></span> Cumulative Net Change</h6>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px;"><canvas id="cumChart"></canvas></div>
    </div>
  </div>

</div>

<!-- ── Charts row 2 ──────────────────────────────────────────── -->
<div class="grid grid-12-8" style="margin-bottom:16px;">

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon orange"><i class="bi bi-graph-up-arrow"></i></span> Daily Sales Revenue</h6>
      <span class="card-meta" style="color:var(--brand-orange);font-weight:700;">KES <?= number_format($total_rev, 0) ?> total</span>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:240px;"><canvas id="revChart"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon blue"><i class="bi bi-calendar3"></i></span> Monthly Revenue</h6>
      <span class="card-meta">Last 6 months</span>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:240px;"><canvas id="monChart"></canvas></div>
    </div>
  </div>

</div>

<!-- ── Inventory breakdown ────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Inventory Breakdown</span>
</div>

<div class="grid grid-8-4" style="margin-bottom:16px;">

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon orange"><i class="bi bi-trophy-fill"></i></span> Top Products by Stock Value</h6>
    </div>
    <?php if (empty($top_value)): ?>
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
        <div class="empty-state-title">No data</div>
      </div>
    </div>
    <?php else:
      $max_val = max(array_column($top_value, 'stock_value'));
    ?>
    <div class="card-body-flush table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th class="td-rank">#</th>
            <th>Product</th>
            <th style="text-align:right;">Qty</th>
            <th style="text-align:right;">Cost/Unit</th>
            <th style="text-align:right;">Stock Value</th>
            <th style="width:120px;">Share</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($top_value as $i => $r):
          $pct = $max_val > 0 ? round($r['stock_value'] / $max_val * 100) : 0;
          $rank_cls = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : ''));
        ?>
          <tr>
            <td class="td-rank"><span class="rank-badge <?= $rank_cls ?>"><?= $i+1 ?></span></td>
            <td class="fw-600 truncate" style="max-width:200px;"><?= htmlspecialchars($r['product']) ?></td>
            <td style="text-align:right;"><?= number_format((float)$r['qty']) ?></td>
            <td style="text-align:right;" class="text-secondary">KES <?= number_format((float)$r['cost_price'], 0) ?></td>
            <td style="text-align:right;" class="fw-700 text-blue">KES <?= number_format((float)$r['stock_value'], 0) ?></td>
            <td>
              <div class="progress-bar-wrap">
                <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
                <span class="progress-bar-label"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="card-title"><span class="card-title-icon orange"><i class="bi bi-pie-chart-fill"></i></span> Stock by Category</h6>
    </div>
    <div class="card-body">
      <?php if (empty($categories)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-pie-chart"></i></div>
        <div class="empty-state-title">No categories</div>
      </div>
      <?php else: ?>
      <div class="chart-container" style="height:280px;"><canvas id="catChart"></canvas></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Low stock alerts ──────────────────────────────────────── -->
<?php if (!empty($low_stock)): ?>
<div class="section-header">
  <span class="section-title">Low Stock Alerts</span>
  <a href="/low-stock.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-right"></i> View all</a>
</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h6 class="card-title"><span class="card-title-icon" style="background:var(--color-warning-bg);color:var(--color-warning);"><i class="bi bi-exclamation-triangle-fill"></i></span> Items At or Below Alert Quantity</h6>
    <span class="chip chip-amber"><?= count($low_stock) ?> items</span>
  </div>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th style="text-align:center;">Alert Qty</th>
          <th style="text-align:center;">In Stock</th>
          <th style="text-align:center;">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($low_stock as $r): ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="chip chip-slate"><?= htmlspecialchars($r['category'] ?? '—') ?></span></td>
          <td style="text-align:center;"><?= number_format((float)$r['alert_quantity']) ?></td>
          <td style="text-align:center;" class="fw-700 <?= (float)$r['qty'] <= 0 ? 'text-danger' : 'text-warning' ?>"><?= number_format((float)$r['qty']) ?></td>
          <td style="text-align:center;">
            <?php if ((float)$r['qty'] <= 0): ?>
              <span class="status-chip out">Out of Stock</span>
            <?php elseif ((float)$r['alert_quantity'] > 0 && (float)$r['qty'] / (float)$r['alert_quantity'] <= 0.5): ?>
              <span class="status-chip critical">Critical</span>
            <?php else: ?>
              <span class="status-chip low">Low</span>
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
