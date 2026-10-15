<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Order Details';
$page_subtitle = 'Review a call center order';

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: call-center-orders.php'); exit; }

$save_error = null;

// Once placed, an order is locked -- notes are the only thing that can change.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        update_call_center_order_notes($id, trim($_POST['notes'] ?? ''));
        header('Location: call-center-order-view.php?id=' . $id . '&saved=1');
        exit;
    } catch (Exception $e) {
        $save_error = $e->getMessage();
    }
}

try {
    $order           = get_call_center_order($id);
    $payment_methods = get_call_center_payment_methods();
    $mpesa_match     = ($order && !empty($order['mpesa_receipt_number'])) ? find_mpesa_transaction($order['mpesa_receipt_number']) : null;
    $db_error        = null;
} catch (Exception $e) {
    $order           = null;
    $payment_methods = [];
    $mpesa_match     = null;
    $db_error        = $e->getMessage();
}

if (!$order && !$db_error) { header('Location: call-center-orders.php'); exit; }

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php else: ?>

<?php if (isset($_GET['saved'])): ?>
<div class="ck-alert ok"><i class="bi bi-check-circle-fill"></i><div>Notes updated.</div></div>
<?php endif; ?>
<?php if ($save_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><?= htmlspecialchars($save_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="ck-page-title">Order <?= htmlspecialchars($order['order_no']) ?></h1>
    <p class="ck-page-sub mb-0">Placed <?= date('d M Y, H:i', strtotime($order['created_at'])) ?><?= $order['agent_name'] ? ' by ' . htmlspecialchars($order['agent_name']) : '' ?></p>
  </div>
  <a href="call-center-orders.php" class="btn btn-ck-ghost btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-person"></i></span>Customer</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <p class="ck-kpi-label mb-1">Customer Name</p>
            <div class="fw-semibold"><?= htmlspecialchars($order['customer_name']) ?></div>
          </div>
          <div class="col-md-6">
            <p class="ck-kpi-label mb-1">Phone Number</p>
            <div class="fw-semibold"><?= htmlspecialchars($order['customer_phone']) ?></div>
          </div>
          <div class="col-12">
            <p class="ck-kpi-label mb-1">Delivery Address</p>
            <div><?= $order['customer_address'] !== null && $order['customer_address'] !== '' ? htmlspecialchars($order['customer_address']) : '<span class="text-muted">&mdash;</span>' ?></div>
          </div>
          <div class="col-md-6">
            <p class="ck-kpi-label mb-1">Taken By</p>
            <div><?= $order['agent_name'] ? htmlspecialchars($order['agent_name']) : '<span class="text-muted">&mdash;</span>' ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-truck"></i></span>Status</h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <p class="ck-kpi-label mb-1">Mode of Delivery</p>
            <span class="ck-chip <?= $order['delivery_method'] === 'parcel' ? 'ck-chip-orange' : 'ck-chip-blue' ?>"><?= $order['delivery_method'] === 'parcel' ? 'Parcel' : 'Delivery' ?></span>
          </div>
          <div class="col-md-4">
            <p class="ck-kpi-label mb-1">Payment Status</p>
            <span class="ck-chip <?= $order['payment_status'] === 'paid' ? 'ck-chip-green' : 'ck-chip-amber' ?>"><?= $order['payment_status'] === 'paid' ? 'Paid' : 'Payment on Delivery' ?></span>
          </div>
          <div class="col-md-4">
            <p class="ck-kpi-label mb-1">Order Status</p>
            <?php if ($order['order_status'] === 'pending'): ?><span class="ck-chip ck-chip-slate">Pending</span>
            <?php elseif ($order['order_status'] === 'converted'): ?><span class="ck-chip ck-chip-green">Converted to POS Sale</span>
            <?php else: ?><span class="ck-chip ck-chip-red">Cancelled</span><?php endif; ?>
          </div>
          <?php if ($order['payment_status'] === 'paid'): ?>
          <div class="col-md-4">
            <p class="ck-kpi-label mb-1">Payment Method</p>
            <div class="fw-semibold"><?= htmlspecialchars($payment_methods[$order['payment_method']] ?? ($order['payment_method'] ?: '—')) ?></div>
          </div>
          <div class="col-md-8">
            <p class="ck-kpi-label mb-1">M-Pesa Transaction</p>
            <?php if ($mpesa_match): ?>
              <span class="ck-chip ck-chip-green"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($mpesa_match['receipt_number']) ?><?= $mpesa_match['first_name'] ? ' &middot; ' . htmlspecialchars($mpesa_match['first_name']) : '' ?> &middot; KES <?= number_format((float)$mpesa_match['amount']) ?></span>
            <?php elseif (!empty($order['mpesa_receipt_number'])): ?>
              <span class="ck-chip ck-chip-red"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($order['mpesa_receipt_number']) ?> &middot; not found in M-Pesa log</span>
            <?php else: ?>
              <span class="text-muted">&mdash;</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <form method="POST" action="">
      <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
          <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-slate"><i class="bi bi-journal-text"></i></span>Notes</h6>
        </div>
        <div class="card-body">
          <textarea name="notes" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
          <small class="text-muted">Orders can't be changed once placed -- only notes can be updated.</small>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Notes</button>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-green"><i class="bi bi-cart4"></i></span>Order Items</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Product</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Price</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($order['items'] as $it): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($it['product_name']) ?><?= $it['variation_name'] ? '<div class="text-muted" style="font-size:0.75rem;">' . htmlspecialchars($it['variation_name']) . '</div>' : '' ?></td>
              <td class="text-center"><?= (int)$it['quantity'] ?></td>
              <td class="text-end">KES <?= number_format((float)$it['unit_price']) ?></td>
              <td class="text-end fw-bold" style="color:#1d4ed8">KES <?= number_format((float)$it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <?php if ((float)$order['discount_amount'] > 0): ?>
            <tr>
              <td colspan="3" class="text-end text-muted">Discount</td>
              <td class="text-end text-muted">&minus; KES <?= number_format((float)$order['discount_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)$order['shipping_amount'] > 0): ?>
            <tr>
              <td colspan="3" class="text-end text-muted">Shipping</td>
              <td class="text-end text-muted">KES <?= number_format((float)$order['shipping_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <td colspan="3" class="text-end fw-bold">Total</td>
              <td class="text-end fw-bold fs-6" style="color:#1d4ed8">KES <?= number_format((float)$order['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
