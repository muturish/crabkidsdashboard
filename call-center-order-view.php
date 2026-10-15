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

<?php
$dash = '<span class="text-muted">&mdash;</span>';
$items_qty = array_sum(array_map(fn($it) => (int)$it['quantity'], $order['items']));
?>
<div class="cc-view-head">
  <div class="min-w-0">
    <div class="d-flex align-items-center flex-wrap gap-2">
      <h1 class="ck-page-title mb-0">Order <?= htmlspecialchars($order['order_no']) ?></h1>
      <?php if ($order['order_status'] === 'pending'): ?><span class="ck-chip ck-chip-slate">Pending</span>
      <?php elseif ($order['order_status'] === 'converted'): ?><span class="ck-chip ck-chip-green">Converted to POS Sale</span>
      <?php else: ?><span class="ck-chip ck-chip-red">Cancelled</span><?php endif; ?>
      <span class="ck-chip <?= $order['payment_status'] === 'paid' ? 'ck-chip-green' : 'ck-chip-amber' ?>"><?= $order['payment_status'] === 'paid' ? 'Paid' : 'Payment on Delivery' ?></span>
      <span class="ck-chip <?= $order['delivery_method'] === 'parcel' ? 'ck-chip-orange' : 'ck-chip-blue' ?>"><?= $order['delivery_method'] === 'parcel' ? 'Parcel' : 'Delivery' ?></span>
    </div>
    <p class="ck-page-sub mb-0">Placed <?= date('d M Y, H:i', strtotime($order['created_at'])) ?><?= $order['agent_name'] ? ' by ' . htmlspecialchars($order['agent_name']) : '' ?></p>
  </div>
  <a href="call-center-orders.php" class="btn btn-ck-ghost btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body cc-view-card">
        <dl class="cc-kv">
          <dt><i class="bi bi-person"></i>Customer</dt>
          <dd class="fw-semibold"><?= htmlspecialchars($order['customer_name']) ?></dd>

          <dt><i class="bi bi-telephone"></i>Phone</dt>
          <dd class="fw-semibold"><?= htmlspecialchars($order['customer_phone']) ?></dd>

          <dt><i class="bi bi-geo-alt"></i>Address</dt>
          <dd><?= $order['customer_address'] !== null && $order['customer_address'] !== '' ? htmlspecialchars($order['customer_address']) : $dash ?></dd>

          <dt><i class="bi bi-headset"></i>Taken by</dt>
          <dd><?= $order['agent_name'] ? htmlspecialchars($order['agent_name']) : $dash ?></dd>

          <?php if ($order['payment_status'] === 'paid'): ?>
          <dt><i class="bi bi-phone"></i>M-Pesa</dt>
          <dd>
            <?php if ($mpesa_match): ?>
              <span class="ck-chip ck-chip-green"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($mpesa_match['receipt_number']) ?><?= $mpesa_match['first_name'] ? ' &middot; ' . htmlspecialchars($mpesa_match['first_name']) : '' ?> &middot; KES <?= number_format((float)$mpesa_match['amount']) ?></span>
            <?php elseif (!empty($order['mpesa_receipt_number'])): ?>
              <span class="ck-chip ck-chip-red"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($order['mpesa_receipt_number']) ?> &middot; not found in M-Pesa log</span>
            <?php else: ?>
              <?= $dash ?>
            <?php endif; ?>
          </dd>
          <?php endif; ?>
        </dl>

        <form method="POST" action="" class="cc-view-notes">
          <label for="notes" class="cc-view-label"><i class="bi bi-journal-text"></i>Notes <span class="cc-view-hint">only notes can be edited after placing</span></label>
          <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2" placeholder="Add a note…"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
          <div class="text-end mt-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Notes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="cc-view-items-head">
        <span class="cc-view-label mb-0"><i class="bi bi-cart4"></i>Items</span>
        <small class="text-muted"><?= $items_qty ?> pc<?= $items_qty === 1 ? '' : 's' ?></small>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 cc-view-items">
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
              <td class="fw-semibold"><?= htmlspecialchars($it['product_name']) ?><?= $it['variation_name'] ? ' <span class="text-muted fw-normal">&middot; ' . htmlspecialchars($it['variation_name']) . '</span>' : '' ?></td>
              <td class="text-center"><?= (int)$it['quantity'] ?></td>
              <td class="text-end"><?= number_format((float)$it['unit_price']) ?></td>
              <td class="text-end fw-semibold"><?= number_format((float)$it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <?php if ((float)$order['discount_amount'] > 0): ?>
            <tr class="cc-view-sub">
              <td colspan="3" class="text-end">Discount</td>
              <td class="text-end">&minus; <?= number_format((float)$order['discount_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)$order['shipping_amount'] > 0): ?>
            <tr class="cc-view-sub">
              <td colspan="3" class="text-end">Shipping</td>
              <td class="text-end"><?= number_format((float)$order['shipping_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="cc-view-total">
              <td colspan="3" class="text-end">Total</td>
              <td class="text-end">KES <?= number_format((float)$order['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
