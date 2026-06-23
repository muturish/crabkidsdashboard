<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Low Stock Alerts';
$page_subtitle = 'Items at or below alert threshold, including out-of-stock variations';

try {
    $low      = get_low_stock_items();
    $out      = get_out_of_stock_items();
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $low = $out = [];
}

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

<!-- ── Summary KPIs ──────────────────────────────────────────── -->
<div class="row g-3 mb-4" style="max-width:400px;">
  <div class="col-6">
    <div class="ck-kpi kpi-amber h-100">
      <div class="ck-kpi-top"><p class="ck-kpi-label">Low Stock</p><span class="ck-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div>
      <div class="ck-kpi-value"><?= number_format(count($low)) ?></div>
      <div class="ck-kpi-sub">below threshold</div>
    </div>
  </div>
  <div class="col-6">
    <div class="ck-kpi kpi-red h-100">
      <div class="ck-kpi-top"><p class="ck-kpi-label">Out of Stock</p><span class="ck-kpi-icon"><i class="bi bi-x-circle"></i></span></div>
      <div class="ck-kpi-value"><?= number_format(count($out)) ?></div>
      <div class="ck-kpi-sub">zero units</div>
    </div>
  </div>
</div>

<!-- ── Low stock table ───────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <span class="ck-section-title">Low Stock — At or Below Alert Quantity</span>
  <span class="ck-chip ck-chip-amber"><?= count($low) ?> items</span>
</div>

<div class="card mb-4">
  <?php if (empty($low)): ?>
  <div class="card-body">
    <div class="ck-empty">
      <div class="ck-empty-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div>
      <p class="ck-empty-title">All good!</p>
      <p class="ck-empty-desc">All items are above their alert threshold.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Product</th>
          <th>Variation</th>
          <th class="hide-sm">Category</th>
          <th class="text-center">Alert Qty</th>
          <th class="text-center">In Stock</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($low as $r):
        $qty   = (float)$r['qty'];
        $alert = (float)$r['alert_quantity'];
        $pct   = $alert > 0 ? ($qty / $alert * 100) : 100;
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td class="text-muted hide-sm"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
          <td class="text-center"><?= number_format($alert) ?></td>
          <td class="text-center fw-bold <?= $qty<=0?'text-danger':'text-warning' ?>"><?= number_format($qty) ?></td>
          <td class="text-center">
            <?php if ($qty <= 0): ?>
              <span class="ck-status out">Out of Stock</span>
            <?php elseif ($pct <= 25): ?>
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
  <?php endif; ?>
</div>

<!-- ── Out of stock table ────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <span class="ck-section-title">Out of Stock</span>
  <span class="ck-chip ck-chip-red"><?= count($out) ?> variations</span>
</div>

<div class="card">
  <?php if (empty($out)): ?>
  <div class="card-body">
    <div class="ck-empty">
      <div class="ck-empty-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div>
      <p class="ck-empty-title">No out-of-stock items</p>
      <p class="ck-empty-desc">Great job keeping the shelves stocked!</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Product</th>
          <th>Variation</th>
          <th class="hide-sm">Category</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($out as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td class="text-muted hide-sm"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
          <td class="text-center"><span class="ck-status out">Out of Stock</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
