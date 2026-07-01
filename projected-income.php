<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Projected Income Growth';
$page_subtitle = "Forecast the company's revenue over the next three years, based on your last 6 months of actual sales.";

$this_year = (int)date('Y');

$fy = isset($_GET['fy']) && ctype_digit($_GET['fy']) ? (int)$_GET['fy'] : $this_year;
if ($fy < $this_year - 1 || $fy > $this_year + 3) $fy = $this_year;

$division_id = isset($_GET['division']) && ctype_digit($_GET['division']) ? (int)$_GET['division'] : null;

try {
    $opts     = get_filter_options();
    $trend    = get_recent_sales_trend(6, $division_id);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $opts     = ['brands' => [], 'categories' => [], 'sub_categories' => []];
    $trend    = [];
}

// Derive the projection defaults from the actual 6-month trend:
// - Current Annual Income = 6-month average revenue, annualized (run-rate).
// - Growth Rate = second-half vs first-half average, annualized, clamped to a sane range.
$revenues     = array_column($trend, 'revenue');
$months_count = count($revenues);
$avg_monthly  = $months_count ? array_sum($revenues) / $months_count : 0;
$default_income = $avg_monthly * 12;

$half        = intdiv($months_count, 2);
$first_half  = array_slice($revenues, 0, $half);
$second_half = array_slice($revenues, $half);
$avg_first   = $half ? array_sum($first_half) / $half : 0;
$avg_second  = $half ? array_sum($second_half) / count($second_half) : 0;

if ($avg_first > 0 && $half > 0) {
    $growth_over_half = ($avg_second - $avg_first) / $avg_first;
    $periods_per_year = 12 / $half;
    $default_growth   = (pow(1 + $growth_over_half, $periods_per_year) - 1) * 100;
} else {
    $default_growth = 0;
}
$default_growth = round(max(-90, min(300, $default_growth)), 1);

$j_hist_labels = json_encode(array_column($trend, 'label'));
$j_hist_data   = json_encode(array_map(fn($v) => round($v), $revenues));

$inline_scripts = <<<JS
(function(){var c=document.getElementById('historyChart');if(!c)return;new Chart(c,{type:'bar',data:{labels:{$j_hist_labels},datasets:[{label:'Actual Revenue (KES)',data:{$j_hist_data},backgroundColor:'#1d4ed8',borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return 'KES '+ctx.parsed.y.toLocaleString();}}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{callback:function(v){return 'KES '+(v/1000).toFixed(0)+'k';}}}}}});})();
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb mb-0" style="font-size:0.8rem;">
    <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Projected Income Growth</li>
  </ol>
</nav>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1>
    <p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p>
  </div>
  <div class="d-flex align-items-center gap-2 ck-no-print">
    <button type="button" id="btn-print" class="btn btn-ck-ghost btn-sm"><i class="bi bi-printer me-1"></i>Print</button>
    <button type="button" id="btn-export-excel" class="btn btn-ck-ghost btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel</button>
    <button type="button" id="btn-export-pdf" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
  </div>
</div>

<div class="ck-pills mb-3">
  <span class="ck-pill"><i class="bi bi-calendar3" style="color:#1d4ed8"></i> <?= date('l, d F Y') ?></span>
  <span class="ck-pill"><i class="bi bi-bar-chart-line" style="color:#f97316"></i> Avg. Monthly (6mo): <strong>KES <?= number_format($avg_monthly) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-graph-up-arrow" style="color:#059669"></i> Trend-Derived Growth: <strong><?= ($default_growth >= 0 ? '+' : '') . $default_growth ?>%</strong></span>
  <span class="ck-pill"><i class="bi bi-cash-coin text-muted"></i> Currency: <strong>KES</strong></span>
</div>

<!-- Filters (server-side: re-baselines the 6-month trend from real sales data) -->
<form method="GET" action="" class="ck-filter ck-no-print">
  <select name="fy" id="sel-fy" class="form-select form-select-sm w-auto">
    <?php for ($y = $this_year - 1; $y <= $this_year + 3; $y++): ?>
      <option value="<?= $y ?>" <?= $fy === $y ? 'selected' : '' ?>>FY <?= $y ?></option>
    <?php endfor; ?>
  </select>

  <select name="division" class="form-select form-select-sm w-auto">
    <option value="">All Divisions</option>
    <?php foreach ($opts['brands'] as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $division_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="currency" class="form-select form-select-sm w-auto">
    <option value="KES" selected>KES (Kenyan Shilling)</option>
  </select>

  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <a href="projected-income.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<!-- Historical Performance: the real data the projection is built on -->
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Historical Performance</span><span class="ck-chip ck-chip-blue">last 6 complete months</span></div>
<div class="card mb-4 ck-no-print">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-clock-history"></i></span>Actual Monthly Revenue</h6>
    <small class="text-muted">This is what the assumptions below are derived from</small>
  </div>
  <div class="card-body">
    <?php if (empty($trend) || $avg_monthly <= 0): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div><p class="mb-0 fw-semibold">No sales recorded in the last 6 months</p><small class="text-muted">Enter assumptions manually below.</small></div>
    <?php else: ?>
      <div class="ck-chart" style="height:220px;"><canvas id="historyChart"></canvas></div>
      <div class="row g-3 mt-1 text-center">
        <div class="col-4">
          <div class="ck-kpi-label mb-1">Avg. Monthly Revenue</div>
          <div class="fw-bold" style="color:#1d4ed8">KES <?= number_format($avg_monthly) ?></div>
        </div>
        <div class="col-4">
          <div class="ck-kpi-label mb-1">First 3mo → Last 3mo</div>
          <div class="fw-bold">KES <?= number_format($avg_first) ?> → KES <?= number_format($avg_second) ?></div>
        </div>
        <div class="col-4">
          <div class="ck-kpi-label mb-1">Annualized Growth</div>
          <div class="fw-bold" style="color:#059669"><?= ($default_growth >= 0 ? '+' : '') . $default_growth ?>%</div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Projection Inputs -->
<div class="card mb-4 ck-no-print">
  <div class="card-header d-flex align-items-center gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-sliders"></i></span>Projection Inputs</h6>
    <small class="text-muted">Both defaults below are calculated from actual sales above — adjust freely to model a different assumption</small>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label ck-kpi-label">Current Annual Income (KES)</label>
        <input type="number" id="inp-current-income" class="form-control form-control-sm" min="0" step="1000" value="<?= (int)$default_income ?>">
        <small class="text-muted">6-mo avg. × 12</small>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label ck-kpi-label">Annual Growth Rate (%)</label>
        <input type="number" id="inp-growth-rate" class="form-control form-control-sm" min="-100" max="500" step="0.5" value="<?= (float)$default_growth ?>">
        <small class="text-muted">from 6-mo trend</small>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label ck-kpi-label">Additional Annual Income</label>
        <input type="number" id="inp-additional-income" class="form-control form-control-sm" min="0" step="1000" value="0">
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label ck-kpi-label">Inflation Rate (%)</label>
        <input type="number" id="inp-inflation-rate" class="form-control form-control-sm" min="0" max="100" step="0.1" value="0">
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <label class="form-label ck-kpi-label">Projection Period (yrs)</label>
        <input type="number" id="inp-period" class="form-control form-control-sm" min="1" max="10" step="1" value="3">
      </div>
      <div class="col-6 col-md-4 col-lg-2 d-flex align-items-end">
        <button type="button" id="btn-calculate" class="btn btn-primary btn-sm w-100"><i class="bi bi-calculator me-1"></i>Calculate Projection</button>
      </div>
    </div>
  </div>
</div>

<!-- Everything below is what gets captured for PDF export / printed -->
<div id="ck-report-area">

  <!-- KPI Summary -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-slate">
        <div class="ck-kpi-head"><p class="ck-kpi-label">Current Income</p><span class="ck-kpi-icon"><i class="bi bi-cash-stack"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-current-val">KES 0</div>
        <div class="ck-kpi-sub" id="kpi-current-trend">baseline</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-blue">
        <div class="ck-kpi-head"><p class="ck-kpi-label">Year 1</p><span class="ck-kpi-icon"><i class="bi bi-graph-up"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-y1-val">KES 0</div>
        <div class="ck-kpi-sub" id="kpi-y1-trend"></div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-sky">
        <div class="ck-kpi-head"><p class="ck-kpi-label">Year 2</p><span class="ck-kpi-icon"><i class="bi bi-graph-up"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-y2-val">KES 0</div>
        <div class="ck-kpi-sub" id="kpi-y2-trend"></div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-green">
        <div class="ck-kpi-head"><p class="ck-kpi-label">Year 3</p><span class="ck-kpi-icon"><i class="bi bi-graph-up-arrow"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-y3-val">KES 0</div>
        <div class="ck-kpi-sub" id="kpi-y3-trend"></div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-orange">
        <div class="ck-kpi-head"><p class="ck-kpi-label">Total Growth</p><span class="ck-kpi-icon"><i class="bi bi-percent"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-growth-val">0%</div>
        <div class="ck-kpi-sub" id="kpi-growth-trend"></div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="ck-kpi kpi-amber">
        <div class="ck-kpi-head"><p class="ck-kpi-label">CAGR</p><span class="ck-kpi-icon"><i class="bi bi-speedometer2"></i></span></div>
        <div class="ck-kpi-val sm" id="kpi-cagr-val">0%</div>
        <div class="ck-kpi-sub" id="kpi-cagr-trend"></div>
      </div>
    </div>
  </div>

  <!-- Chart -->
  <div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Revenue Projection</span><span class="ck-chip ck-chip-blue">nominal vs. real</span></div>
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-graph-up-arrow"></i></span>Projected Income Trend</h6>
      <small class="text-muted">Current year → projection period</small>
    </div>
    <div class="card-body">
      <div class="ck-chart" style="height:320px;"><canvas id="projChart"></canvas></div>
    </div>
  </div>

  <!-- Projection Table -->
  <div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Projection Table</span></div>
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-table"></i></span>Year-by-Year Projection</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Financial Year</th>
            <th class="text-end">Projected Income</th>
            <th class="text-end">Annual Increase</th>
            <th class="text-end">Growth %</th>
            <th class="text-end">Cumulative Revenue</th>
          </tr>
        </thead>
        <tbody id="proj-table-body"></tbody>
        <tfoot>
          <tr class="fw-bold">
            <td>Total</td>
            <td class="text-end" id="tf-total-income">—</td>
            <td class="text-end" id="tf-total-increase">—</td>
            <td class="text-end">—</td>
            <td class="text-end" id="tf-total-cumulative">—</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Scenario Analysis -->
  <div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Scenario Analysis</span><span class="ck-chip ck-chip-slate">same assumptions, different growth rates</span></div>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-slate"><i class="bi bi-shield-check"></i></span>Conservative <small class="text-muted fw-normal ms-1">10%</small></h6>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 1</span><strong id="scn-conservative-y1">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 2</span><strong id="scn-conservative-y2">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 3</span><strong id="scn-conservative-y3">—</strong></div>
          <hr>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Total Revenue</span><strong id="scn-conservative-total">—</strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted small">Overall Growth</span><strong id="scn-conservative-growth">—</strong></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100" style="border-color:#93c5fd;">
        <div class="card-header d-flex align-items-center gap-2">
          <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-bullseye"></i></span>Expected <small class="text-muted fw-normal ms-1">20%</small></h6>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 1</span><strong id="scn-expected-y1">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 2</span><strong id="scn-expected-y2">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 3</span><strong id="scn-expected-y3">—</strong></div>
          <hr>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Total Revenue</span><strong id="scn-expected-total">—</strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted small">Overall Growth</span><strong id="scn-expected-growth">—</strong></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-rocket-takeoff"></i></span>Optimistic <small class="text-muted fw-normal ms-1">40%</small></h6>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 1</span><strong id="scn-optimistic-y1">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 2</span><strong id="scn-optimistic-y2">—</strong></div>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Year 3</span><strong id="scn-optimistic-y3">—</strong></div>
          <hr>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted small">Total Revenue</span><strong id="scn-optimistic-total">—</strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted small">Overall Growth</span><strong id="scn-optimistic-growth">—</strong></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Executive Insights -->
  <div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Executive Insights</span></div>
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-green"><i class="bi bi-stars"></i></span>Summary &amp; Recommendation</h6>
    </div>
    <div class="card-body">
      <ul class="list-unstyled mb-3" id="insights-list"></ul>
      <div class="ck-alert info mb-0" id="recommendation-box"><i class="bi bi-lightbulb-fill"></i><div>Enter your assumptions above to generate insights.</div></div>
    </div>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
<script src="/assets/js/projected-income.js" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
