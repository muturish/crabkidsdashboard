<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Low Stock Alerts';
$page_subtitle = 'Variations at or below their alert threshold, plus out-of-stock items';

try {
    $low      = get_low_stock_items();
    $out      = get_out_of_stock_items();
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $low      = [];
    $out      = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
    Database not connected: <?= htmlspecialchars($db_error) ?>
</div>
<?php endif; ?>

<!-- ── Summary banners ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="kpi-card orange">
            <div class="kpi-icon orange"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <p class="kpi-label">Low Stock Items</p>
                <p class="kpi-value"><?= number_format(count($low)) ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="kpi-card red">
            <div class="kpi-icon red"><i class="bi bi-x-circle"></i></div>
            <div>
                <p class="kpi-label">Out of Stock</p>
                <p class="kpi-value"><?= number_format(count($out)) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Low stock table ─────────────────────────────────────── -->
<div class="dash-card mb-4">
    <div class="dash-card-header">
        <h6>
            <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--ck-orange)"></i>
            Low Stock — At or Below Alert Quantity
        </h6>
        <span class="badge rounded-pill" style="background:var(--ck-orange);color:#fff;">
            <?= count($low) ?> items
        </span>
    </div>
    <div class="dash-card-body p-0">
        <?php if (empty($low)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                <p class="text-muted mb-0">All items are above their alert threshold.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variation</th>
                        <th>Category</th>
                        <th class="text-center">Alert Qty</th>
                        <th class="text-center">In Stock</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($low as $r): ?>
                    <?php
                        $qty   = (float)$r['qty'];
                        $alert = (float)$r['alert_quantity'];
                        $pct   = $alert > 0 ? round(($qty / $alert) * 100) : 100;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['variation']) ?></span></td>
                        <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                        <td class="text-center"><?= number_format($alert) ?></td>
                        <td class="text-center fw-bold <?= $qty <= 0 ? 'text-danger' : 'text-warning' ?>">
                            <?= number_format($qty) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($qty <= 0): ?>
                                <span class="badge badge-out">Out of Stock</span>
                            <?php elseif ($pct <= 25): ?>
                                <span class="badge badge-low">Critical</span>
                            <?php else: ?>
                                <span class="badge badge-low">Low</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Out of stock table ──────────────────────────────────── -->
<div class="dash-card" id="out-of-stock">
    <div class="dash-card-header">
        <h6>
            <i class="bi bi-x-circle-fill me-2 text-danger"></i>
            Out of Stock
        </h6>
        <span class="badge rounded-pill bg-danger">
            <?= count($out) ?> variations
        </span>
    </div>
    <div class="dash-card-body p-0">
        <?php if (empty($out)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                <p class="text-muted mb-0">No out-of-stock items. Great job!</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variation</th>
                        <th>Category</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($out as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['product']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['variation']) ?></span></td>
                        <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                        <td class="text-center">
                            <span class="badge badge-out">Out of Stock</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
