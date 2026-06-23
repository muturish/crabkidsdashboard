<?php
require_once __DIR__ . '/bootstrap.php';

$lowStock = get_low_stock_items($pdo, $bizId, 500);
$outOfStock = get_out_of_stock_items($pdo, $bizId, 500);

$pageTitle = 'Low Stock Alerts';
$activePage = 'low_stock';
require __DIR__ . '/includes/header.php';
?>

<div class="page-heading">Low Stock Alerts</div>
<div class="page-subheading">Items at or below their reorder point, and items already out of stock.</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Low stock items</div>
                    <div class="kpi-value"><?= format_number(count($lowStock)) ?></div>
                </div>
                <div class="kpi-icon amber"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
            <div class="kpi-sub">At or below alert quantity</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Out of stock items</div>
                    <div class="kpi-value"><?= format_number(count($outOfStock)) ?></div>
                </div>
                <div class="kpi-icon coral"><i class="bi bi-x-octagon"></i></div>
            </div>
            <div class="kpi-sub">Zero or negative quantity</div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-title">Low stock</div>
    <div class="panel-subtitle">Sorted by how close each item is to running out</div>
    <?php if (empty($lowStock)): ?>
        <p class="text-muted small mb-0">Nothing is below its alert quantity right now. Nice work.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-low-stock mb-0">
            <thead>
            <tr>
                <th>Product</th>
                <th>Variation</th>
                <th>SKU</th>
                <th>Category</th>
                <th class="text-end">On hand</th>
                <th class="text-end">Alert at</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lowStock as $row): ?>
                <?php $qty = (float)$row['qty_available']; ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['variation_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['variation_sku'] ?? $row['product_sku']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= format_number($qty) ?></td>
                    <td class="text-end text-muted"><?= format_number((float)$row['alert_quantity']) ?></td>
                    <td class="text-end">
                        <?php if ($qty <= 0): ?>
                            <span class="badge badge-stock-out">Out of stock</span>
                        <?php else: ?>
                            <span class="badge badge-stock-low">Low</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel" id="out-of-stock">
    <div class="panel-title">Out of stock</div>
    <div class="panel-subtitle">All stock-tracked items currently at zero or negative quantity (includes items with no alert threshold set)</div>
    <?php if (empty($outOfStock)): ?>
        <p class="text-muted small mb-0">Everything is in stock.</p>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-low-stock mb-0">
            <thead>
            <tr>
                <th>Product</th>
                <th>Variation</th>
                <th>SKU</th>
                <th>Category</th>
                <th class="text-end">On hand</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($outOfStock as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['variation_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['variation_sku'] ?? $row['product_sku']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                    <td class="text-end fw-semibold text-danger"><?= format_number((float)$row['qty_available']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
