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
<div class="alert-banner alert-error">
  <i class="bi bi-x-circle-fill"></i>
  <div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div>
</div>
<?php endif; ?>

<!-- ── Page header ───────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <div class="page-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></div>
  </div>
</div>

<!-- ── Summary KPIs ─────────────────────────────────────────── -->
<div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);max-width:400px;margin-bottom:24px;">
  <div class="kpi-card kpi-amber">
    <div class="kpi-card-top">
      <span class="kpi-label">Low Stock</span>
      <span class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
    </div>
    <div class="kpi-value"><?= number_format(count($low)) ?></div>
    <div class="kpi-sub">items below threshold</div>
  </div>
  <div class="kpi-card kpi-red">
    <div class="kpi-card-top">
      <span class="kpi-label">Out of Stock</span>
      <span class="kpi-icon"><i class="bi bi-x-circle"></i></span>
    </div>
    <div class="kpi-value"><?= number_format(count($out)) ?></div>
    <div class="kpi-sub">zero units available</div>
  </div>
</div>

<!-- ── Low stock table ──────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Low Stock — At or Below Alert Quantity</span>
  <span class="chip chip-amber"><?= count($low) ?> items</span>
</div>

<div class="card" style="margin-bottom:20px;">
  <?php if (empty($low)): ?>
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-state-icon" style="background:var(--color-success-bg);color:var(--color-success);"><i class="bi bi-check-circle-fill"></i></div>
      <div class="empty-state-title">All good!</div>
      <div class="empty-state-desc">All items are above their alert threshold.</div>
    </div>
  </div>
  <?php else: ?>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Variation</th>
          <th>Category</th>
          <th style="text-align:center;">Alert Qty</th>
          <th style="text-align:center;">In Stock</th>
          <th style="text-align:center;">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($low as $r):
        $qty   = (float)$r['qty'];
        $alert = (float)$r['alert_quantity'];
        $pct   = $alert > 0 ? ($qty / $alert * 100) : 100;
      ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="chip chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td class="text-secondary"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
          <td style="text-align:center;"><?= number_format($alert) ?></td>
          <td style="text-align:center;" class="fw-700 <?= $qty <= 0 ? 'text-danger' : 'text-warning' ?>"><?= number_format($qty) ?></td>
          <td style="text-align:center;">
            <?php if ($qty <= 0): ?>
              <span class="status-chip out">Out of Stock</span>
            <?php elseif ($pct <= 25): ?>
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
  <?php endif; ?>
</div>

<!-- ── Out of stock table ────────────────────────────────────── -->
<div class="section-header">
  <span class="section-title">Out of Stock</span>
  <span class="chip chip-red"><?= count($out) ?> variations</span>
</div>

<div class="card">
  <?php if (empty($out)): ?>
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-state-icon" style="background:var(--color-success-bg);color:var(--color-success);"><i class="bi bi-check-circle-fill"></i></div>
      <div class="empty-state-title">No out-of-stock items</div>
      <div class="empty-state-desc">Great job keeping the shelves stocked!</div>
    </div>
  </div>
  <?php else: ?>
  <div class="card-body-flush table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Variation</th>
          <th>Category</th>
          <th style="text-align:center;">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($out as $r): ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="chip chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
          <td class="text-secondary"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
          <td style="text-align:center;"><span class="status-chip out">Out of Stock</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
