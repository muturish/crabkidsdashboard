<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Targets Monitoring';
$page_subtitle = 'Check your next purchase amount against your M-Pesa balance and track the deficit over time';

// Handle delete (GET, redirect after)
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    try { delete_purchase_target((int)$_GET['delete']); } catch (Exception $e) {}
    header('Location: targets-monitoring.php');
    exit;
}

// Handle save (POST, redirect after to avoid resubmission on refresh)
$save_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_amount'], $_POST['mpesa_balance'])) {
    $purchase_amount = is_numeric($_POST['purchase_amount']) ? (float)$_POST['purchase_amount'] : null;
    $mpesa_balance   = is_numeric($_POST['mpesa_balance'])   ? (float)$_POST['mpesa_balance']   : null;
    $notes           = trim($_POST['notes'] ?? '') !== '' ? trim($_POST['notes']) : null;

    if ($purchase_amount === null || $mpesa_balance === null || $purchase_amount < 0 || $mpesa_balance < 0) {
        $save_error = 'Enter valid, non-negative amounts for both fields.';
    } else {
        try {
            save_purchase_target($purchase_amount, $mpesa_balance, $notes);
            header('Location: targets-monitoring.php');
            exit;
        } catch (Exception $e) {
            $save_error = $e->getMessage();
        }
    }
}

try {
    $suggested = get_suggested_purchase_amount();
    $history   = get_purchase_targets(50);
    $db_error  = null;
} catch (Exception $e) {
    $db_error  = $e->getMessage();
    $suggested = 0;
    $history   = [];
}

$latest = $history[0] ?? null;

$j_labels  = json_encode(array_map(fn($r) => date('d M', strtotime($r['created_at'])), array_reverse($history)));
$j_deficit = json_encode(array_map(fn($r) => round((float)$r['deficit']), array_reverse($history)));
$j_target  = json_encode(array_map(fn($r) => round((float)$r['purchase_amount']), array_reverse($history)));
$j_balance = json_encode(array_map(fn($r) => round((float)$r['mpesa_balance']), array_reverse($history)));

$inline_scripts = <<<JS
(function(){var c=document.getElementById('targetChart');if(!c||!{$j_labels}.length)return;new Chart(c,{type:'line',data:{labels:{$j_labels},datasets:[
  {label:'Purchase Target',data:{$j_target},borderColor:'#1d4ed8',backgroundColor:'rgba(29,78,216,.08)',fill:false,tension:0.3},
  {label:'M-Pesa Balance',data:{$j_balance},borderColor:'#059669',backgroundColor:'rgba(5,150,105,.08)',fill:false,tension:0.3},
  {label:'Deficit',data:{$j_deficit},borderColor:'#dc2626',backgroundColor:'rgba(220,38,38,.08)',fill:true,tension:0.3}
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'},tooltip:{callbacks:{label:function(c){return c.dataset.label+': KES '+c.parsed.y.toLocaleString();}}}},scales:{x:{grid:{display:false}},y:{ticks:{callback:function(v){return 'KES '+(v/1000).toFixed(0)+'k';}}}}}});})();

(function(){
  var pa=document.getElementById('inp-purchase-amount'), mb=document.getElementById('inp-mpesa-balance');
  var out=document.getElementById('live-preview');
  function fmt(n){return 'KES '+Math.round(n).toLocaleString();}
  function render(){
    var p=parseFloat(pa.value)||0, m=parseFloat(mb.value)||0;
    var deficit=p-m, pct=p>0?(deficit/p*100):0;
    var ok=deficit<=0;
    out.innerHTML = ok
      ? '<span class="ck-status ok">Fully Funded</span> Surplus of '+fmt(-deficit)+' ('+Math.abs(pct).toFixed(1)+'% above target)'
      : '<span class="ck-status critical">Shortfall</span> Short by '+fmt(deficit)+' ('+pct.toFixed(1)+'% of purchase amount)';
  }
  if(pa&&mb){pa.addEventListener('input',render);mb.addEventListener('input',render);render();}
})();
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
</div>

<!-- Latest check KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="ck-kpi kpi-blue">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Latest Purchase Target</p><span class="ck-kpi-icon"><i class="bi bi-cart4"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= $latest ? number_format((float)$latest['purchase_amount']) : '0' ?></div>
      <div class="ck-kpi-sub"><?= $latest ? date('d M Y, H:i', strtotime($latest['created_at'])) : 'no checks yet' ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi kpi-green">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Latest M-Pesa Balance</p><span class="ck-kpi-icon"><i class="bi bi-phone"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= $latest ? number_format((float)$latest['mpesa_balance']) : '0' ?></div>
      <div class="ck-kpi-sub">as entered</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi <?= $latest && (float)$latest['deficit'] > 0 ? 'kpi-red' : 'kpi-green' ?>">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Deficit</p><span class="ck-kpi-icon"><i class="bi bi-exclamation-diamond"></i></span></div>
      <div class="ck-kpi-val sm">KES <?= $latest ? number_format((float)$latest['deficit']) : '0' ?></div>
      <div class="ck-kpi-sub"><?= $latest && (float)$latest['deficit'] > 0 ? 'short of target' : 'fully funded / surplus' ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi <?= $latest && (float)$latest['deficit'] > 0 ? 'kpi-amber' : 'kpi-green' ?>">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Deficit %</p><span class="ck-kpi-icon"><i class="bi bi-percent"></i></span></div>
      <div class="ck-kpi-val sm"><?= $latest ? number_format((float)$latest['deficit_pct'], 1) : '0.0' ?>%</div>
      <div class="ck-kpi-sub">of purchase amount</div>
    </div>
  </div>
</div>

<!-- Input card -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-calculator"></i></span>New Target Check</h6>
    <small class="text-muted">Purchase amount defaults to the current Restock-to-Minimum cost — adjust freely</small>
  </div>
  <div class="card-body">
    <?php if ($save_error): ?>
      <div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><?= htmlspecialchars($save_error) ?></div></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
          <label class="form-label ck-kpi-label">Next Purchase Total (KES)</label>
          <input type="number" id="inp-purchase-amount" name="purchase_amount" class="form-control form-control-sm" min="0" step="100" value="<?= (int)$suggested ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label ck-kpi-label">M-Pesa Balance (KES)</label>
          <input type="number" id="inp-mpesa-balance" name="mpesa_balance" class="form-control form-control-sm" min="0" step="100" value="0">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label ck-kpi-label">Notes (optional)</label>
          <input type="text" name="notes" class="form-control form-control-sm" maxlength="255" placeholder="e.g. Boys size restock order">
        </div>
        <div class="col-12 col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-save me-1"></i>Save Check</button>
        </div>
      </div>
    </form>
    <div id="live-preview" class="mt-3 d-flex align-items-center gap-2"></div>
  </div>
</div>

<!-- Trend chart -->
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Deficit Trend</span><span class="ck-chip ck-chip-blue"><?= count($history) ?> checks</span></div>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-graph-up-arrow"></i></span>Purchase Target vs. M-Pesa Balance</h6>
    <small class="text-muted">Most recent 50 checks</small>
  </div>
  <div class="card-body">
    <?php if (empty($history)): ?>
      <div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-bar-chart"></i></div><p class="mb-0 fw-semibold">No checks recorded yet</p><small class="text-muted">Save your first target check above.</small></div>
    <?php else: ?>
      <div class="ck-chart" style="height:280px;"><canvas id="targetChart"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<!-- History table -->
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Check History</span></div>
<div class="card">
  <?php if (empty($history)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="mb-0 fw-semibold">No checks recorded yet</p></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th class="text-end">Purchase Target</th>
            <th class="text-end">M-Pesa Balance</th>
            <th class="text-end">Deficit</th>
            <th class="text-end">Deficit %</th>
            <th class="text-center">Status</th>
            <th class="hide-sm">Notes</th>
            <th style="width:40px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $r): $deficit = (float)$r['deficit']; ?>
          <tr>
            <td class="text-muted"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
            <td class="text-end fw-semibold">KES <?= number_format((float)$r['purchase_amount']) ?></td>
            <td class="text-end">KES <?= number_format((float)$r['mpesa_balance']) ?></td>
            <td class="text-end fw-bold" style="color:<?= $deficit > 0 ? '#dc2626' : '#059669' ?>">KES <?= number_format($deficit) ?></td>
            <td class="text-end"><?= number_format((float)$r['deficit_pct'], 1) ?>%</td>
            <td class="text-center">
              <?php if ($deficit > 0): ?><span class="ck-status critical">Shortfall</span>
              <?php else: ?><span class="ck-status ok">Funded</span><?php endif; ?>
            </td>
            <td class="text-muted hide-sm"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
            <td class="text-center">
              <a href="?delete=<?= $r['id'] ?>" class="text-danger text-decoration-none" onclick="return confirm('Delete this check?');" title="Delete"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
