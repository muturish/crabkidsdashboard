<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Call Center Orders';
$page_subtitle = 'Orders taken by phone/WhatsApp, pending conversion into real POS sales';

$from            = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$to              = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$order_status    = in_array($_GET['order_status'] ?? '', ['pending', 'converted', 'cancelled'], true) ? $_GET['order_status'] : null;
$payment_status  = in_array($_GET['payment_status'] ?? '', ['paid', 'pod'], true) ? $_GET['payment_status'] : null;
$delivery_method = in_array($_GET['delivery_method'] ?? '', ['parcel', 'delivery'], true) ? $_GET['delivery_method'] : null;
$search          = trim($_GET['search'] ?? '');

try {
    $orders   = get_call_center_orders([
        'from' => $from, 'to' => $to, 'order_status' => $order_status,
        'payment_status' => $payment_status, 'delivery_method' => $delivery_method, 'search' => $search,
    ]);
    $stats    = get_call_center_order_stats($from, $to);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $orders   = [];
    $stats    = ['total_orders' => 0, 'total_value' => 0, 'pending_count' => 0, 'pod_count' => 0, 'pod_value' => 0];
}

$created = isset($_GET['created']) ? (string)$_GET['created'] : null;

$inline_scripts = <<<'JS'
document.querySelectorAll('[data-days]').forEach(function(b){b.addEventListener('click',function(){var d=parseInt(this.dataset.days),to=new Date(),fr=new Date();fr.setDate(to.getDate()-(d-1));function f(x){return x.toISOString().split('T')[0];}document.getElementById('inp-from').value=f(fr);document.getElementById('inp-to').value=f(to);document.getElementById('filterForm').submit();});});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<?php if ($created): ?>
<div class="ck-alert ok"><i class="bi bi-check-circle-fill"></i><div>Order <strong><?= htmlspecialchars($created) ?></strong> created successfully.</div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
  <a href="call-center-order-new.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Order</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="ck-kpi ck-kpi-compact kpi-blue">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Total Orders</p><span class="ck-kpi-icon"><i class="bi bi-telephone"></i></span></div>
      <div class="ck-kpi-val sm"><?= number_format($stats['total_orders']) ?></div>
      <div class="ck-kpi-sub">KES <?= number_format($stats['total_value']) ?> value</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi ck-kpi-compact kpi-amber">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Pending</p><span class="ck-kpi-icon"><i class="bi bi-hourglass-split"></i></span></div>
      <div class="ck-kpi-val sm"><?= number_format($stats['pending_count']) ?></div>
      <div class="ck-kpi-sub">not yet converted</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi ck-kpi-compact kpi-slate">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Payment on Delivery</p><span class="ck-kpi-icon"><i class="bi bi-cash-coin"></i></span></div>
      <div class="ck-kpi-val sm"><?= number_format($stats['pod_count']) ?></div>
      <div class="ck-kpi-sub">KES <?= number_format($stats['pod_value']) ?> to collect</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ck-kpi ck-kpi-compact kpi-green">
      <div class="ck-kpi-head"><p class="ck-kpi-label">Date Range</p><span class="ck-kpi-icon"><i class="bi bi-calendar-range"></i></span></div>
      <div class="ck-kpi-val sm"><?= date('d M', strtotime($from)) ?> – <?= date('d M', strtotime($to)) ?></div>
      <div class="ck-kpi-sub"><?= date('Y', strtotime($to)) ?></div>
    </div>
  </div>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter flex-wrap">
  <div class="ck-date-wrap">
    <input type="date" id="inp-from" name="from" value="<?= htmlspecialchars($from) ?>">
    <span class="ck-date-sep">→</span>
    <input type="date" id="inp-to" name="to" value="<?= htmlspecialchars($to) ?>">
  </div>
  <select name="order_status" class="form-select form-select-sm w-auto">
    <option value="">All Statuses</option>
    <option value="pending" <?= $order_status === 'pending' ? 'selected' : '' ?>>Pending</option>
    <option value="converted" <?= $order_status === 'converted' ? 'selected' : '' ?>>Converted</option>
    <option value="cancelled" <?= $order_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
  </select>
  <select name="payment_status" class="form-select form-select-sm w-auto">
    <option value="">All Payments</option>
    <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
    <option value="pod" <?= $payment_status === 'pod' ? 'selected' : '' ?>>Payment on Delivery</option>
  </select>
  <select name="delivery_method" class="form-select form-select-sm w-auto">
    <option value="">All Delivery Modes</option>
    <option value="delivery" <?= $delivery_method === 'delivery' ? 'selected' : '' ?>>Delivery</option>
    <option value="parcel" <?= $delivery_method === 'parcel' ? 'selected' : '' ?>>Parcel</option>
  </select>
  <input type="text" name="search" class="form-control form-control-sm w-auto" placeholder="Order#, customer, phone" value="<?= htmlspecialchars($search) ?>">
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets">
    <button type="button" class="ck-p-btn" data-days="7">7D</button>
    <button type="button" class="ck-p-btn" data-days="30">30D</button>
    <button type="button" class="ck-p-btn" data-days="90">90D</button>
  </div>
  <a href="call-center-orders.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-list-ol"></i></span>Orders</h6>
    <small class="text-muted"><span class="badge text-bg-success me-2">&nbsp;</span>Notes updated &middot; <?= number_format(count($orders)) ?> shown</small>
  </div>
  <?php if (empty($orders)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="mb-0 fw-semibold">No orders in this range</p></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Order#</th>
            <th>Date</th>
            <th>Customer</th>
            <th class="hide-sm">Phone</th>
            <th class="text-center">Items</th>
            <th class="text-end">Total</th>
            <th>Delivery</th>
            <th>Payment</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <?php $notes_updated = !empty($o['notes_updated_at']); ?>
          <tr<?= $notes_updated ? ' class="table-success" title="Notes updated ' . date('d M Y, H:i', strtotime($o['notes_updated_at'])) . '"' : '' ?>>
            <td class="fw-semibold"><a href="call-center-order-view.php?id=<?= (int)$o['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($o['order_no']) ?></a><?php if ($notes_updated): ?> <i class="bi bi-journal-check text-success" title="Notes updated"></i><?php endif; ?></td>
            <td class="text-muted"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
            <td><?= htmlspecialchars($o['customer_name']) ?></td>
            <td class="hide-sm text-muted"><?= htmlspecialchars($o['customer_phone']) ?></td>
            <td class="text-center"><?= (int)$o['item_count'] ?></td>
            <td class="text-end fw-bold" style="color:#1d4ed8">KES <?= number_format((float)$o['total_amount']) ?></td>
            <td><span class="ck-chip <?= $o['delivery_method'] === 'parcel' ? 'ck-chip-orange' : 'ck-chip-blue' ?>"><?= $o['delivery_method'] === 'parcel' ? 'Parcel' : 'Delivery' ?></span></td>
            <td><span class="ck-chip <?= $o['payment_status'] === 'paid' ? 'ck-chip-green' : 'ck-chip-amber' ?>"><?= $o['payment_status'] === 'paid' ? 'Paid' : 'Payment on Delivery' ?></span></td>
            <td>
              <?php if ($o['order_status'] === 'pending'): ?><span class="ck-chip ck-chip-slate">Pending</span>
              <?php elseif ($o['order_status'] === 'converted'): ?><span class="ck-chip ck-chip-green">Converted</span>
              <?php else: ?><span class="ck-chip ck-chip-red">Cancelled</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
