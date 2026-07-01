<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Low Stock Alerts';
$page_subtitle = 'Items at or below alert threshold, including out-of-stock variations';

$min_stock    = isset($_GET['min']) && is_numeric($_GET['min']) && $_GET['min'] > 0 ? (float)$_GET['min'] : 4;
$category_id  = isset($_GET['category']) && ctype_digit($_GET['category']) ? (int)$_GET['category'] : null;
$sub_category = isset($_GET['subcat']) && $_GET['subcat'] !== '' ? $_GET['subcat'] : null;

try {
    $opts    = get_filter_options();
    $low     = get_low_stock_items(200, $category_id, $sub_category);
    $out     = get_out_of_stock_items(200, $category_id, $sub_category);
    $restock = get_restock_requirements($min_stock, $category_id, $sub_category);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $opts = ['brands' => [], 'categories' => [], 'sub_categories' => []];
    $low = $out = $restock = [];
}

$restock_units = array_sum(array_column($restock, 'shortfall'));
$restock_cost  = array_sum(array_column($restock, 'restock_cost'));

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="ck-kpi kpi-amber"><div class="ck-kpi-head"><p class="ck-kpi-label">Low Stock</p><span class="ck-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div><div class="ck-kpi-val"><?= number_format(count($low)) ?></div><div class="ck-kpi-sub">below threshold</div></div></div>
  <div class="col-6 col-md-3"><div class="ck-kpi kpi-red"><div class="ck-kpi-head"><p class="ck-kpi-label">Out of Stock</p><span class="ck-kpi-icon"><i class="bi bi-x-circle"></i></span></div><div class="ck-kpi-val"><?= number_format(count($out)) ?></div><div class="ck-kpi-sub">zero units</div></div></div>
  <div class="col-6 col-md-3"><div class="ck-kpi kpi-orange"><div class="ck-kpi-head"><p class="ck-kpi-label">Units Needed</p><span class="ck-kpi-icon"><i class="bi bi-box-seam"></i></span></div><div class="ck-kpi-val"><?= number_format($restock_units) ?></div><div class="ck-kpi-sub">to reach <?= number_format($min_stock) ?> pairs each</div></div></div>
  <div class="col-6 col-md-3"><div class="ck-kpi kpi-green"><div class="ck-kpi-head"><p class="ck-kpi-label">Restock Cost</p><span class="ck-kpi-icon"><i class="bi bi-cash-coin"></i></span></div><div class="ck-kpi-val sm">KES <?= number_format($restock_cost) ?></div><div class="ck-kpi-sub">at purchase price</div></div></div>
</div>

<form method="GET" action="" class="ck-filter">
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

  <div class="d-flex align-items-center gap-2">
    <label for="inp-min" class="text-muted small mb-0">Min. pairs per item</label>
    <input type="number" id="inp-min" name="min" min="1" step="1" value="<?= htmlspecialchars($min_stock) ?>" class="form-control form-control-sm" style="width:80px;">
  </div>

  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <a href="low-stock.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <span class="ck-label">Restock to Minimum Stock Level</span>
</div>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-green"><i class="bi bi-cash-coin"></i></span>Capital Required to Bring Every Item Up to <?= number_format($min_stock) ?> Pairs</h6>
    <span class="ck-chip ck-chip-amber"><?= count($restock) ?> items</span>
  </div>
  <?php if (empty($restock)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div><p class="mb-0 fw-bold">Nothing to restock!</p><small class="text-muted">Every item already has at least <?= number_format($min_stock) ?> pairs in stock.</small></div></div>
  <?php else:
    $restock_sorted = $restock;
    usort($restock_sorted, fn($a, $b) => $b['restock_cost'] <=> $a['restock_cost']);
  ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Product</th>
            <th>Variation</th>
            <th class="hide-sm">Category</th>
            <th class="text-center">In Stock</th>
            <th class="text-center">Shortfall</th>
            <th class="text-end hide-sm">Unit Cost</th>
            <th class="text-end">Restock Cost</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($restock_sorted as $r): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
            <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
            <td class="text-muted hide-sm"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
            <td class="text-center fw-bold <?= (float)$r['qty']<=0?'text-danger':'text-warning' ?>"><?= number_format((float)$r['qty']) ?></td>
            <td class="text-center">+<?= number_format($r['shortfall']) ?></td>
            <td class="text-end text-muted hide-sm">KES <?= number_format((float)$r['purchase_price']) ?></td>
            <td class="text-end fw-bold" style="color:#059669">KES <?= number_format($r['restock_cost']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td class="fw-semibold">Total</td>
            <td></td>
            <td class="hide-sm"></td>
            <td class="text-center"></td>
            <td class="text-center"><?= number_format($restock_units) ?></td>
            <td class="text-end hide-sm"></td>
            <td class="text-end" style="color:#059669">KES <?= number_format($restock_cost) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Low Stock — At or Below Alert Quantity</span><span class="ck-chip ck-chip-amber"><?= count($low) ?> items</span></div>
<div class="card mb-4">
  <?php if (empty($low)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div><p class="mb-0 fw-bold">All good!</p><small class="text-muted">All items are above their alert threshold.</small></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Product</th><th>Variation</th><th class="hide-sm">Category</th><th class="text-center">Alert Qty</th><th class="text-center">In Stock</th><th class="text-center">Status</th></tr></thead>
        <tbody>
        <?php foreach ($low as $r):
          $qty=$r['qty']; $alert=$r['alert_quantity'];
          $pct=$alert>0?($qty/$alert*100):100;
        ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
            <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
            <td class="text-muted hide-sm"><?= htmlspecialchars($r['category']??'—') ?></td>
            <td class="text-center"><?= number_format((float)$alert) ?></td>
            <td class="text-center fw-bold <?= (float)$qty<=0?'text-danger':'text-warning' ?>"><?= number_format((float)$qty) ?></td>
            <td class="text-center">
              <?php if((float)$qty<=0):?><span class="ck-status out">Out of Stock</span>
              <?php elseif($pct<=25):?><span class="ck-status critical">Critical</span>
              <?php else:?><span class="ck-status low">Low</span><?php endif;?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Out of Stock</span><span class="ck-chip ck-chip-red"><?= count($out) ?> variations</span></div>
<div class="card">
  <?php if (empty($out)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon" style="background:#ecfdf5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div><p class="mb-0 fw-bold">No out-of-stock items!</p><small class="text-muted">Great job keeping shelves stocked.</small></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Product</th><th>Variation</th><th class="hide-sm">Category</th><th class="text-center">Status</th></tr></thead>
        <tbody>
        <?php foreach ($out as $r): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
            <td><span class="ck-chip ck-chip-slate"><?= htmlspecialchars($r['variation']) ?></span></td>
            <td class="text-muted hide-sm"><?= htmlspecialchars($r['category']??'—') ?></td>
            <td class="text-center"><span class="ck-status out">Out of Stock</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
