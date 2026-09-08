<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Transactions';
$page_subtitle = 'All sales with payment method, transaction code, and M-Pesa reconciliation';

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$method = isset($_GET['method']) && $_GET['method'] !== '' ? $_GET['method'] : null;

try {
    $labels = get_payment_method_labels();
    $rows   = get_sales_transactions($from, $to, $method, 500);
    $db_error = null;
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $labels = [];
    $rows   = [];
}

$total_amount = array_sum(array_column($rows, 'amount'));
$mpesa_count  = count(array_filter($rows, fn($r) => !empty($r['mpesa_date'])));

// Method options actually used in the DB's own configured labels, for the filter dropdown.
$method_options = $labels;

$inline_scripts = <<<JS
document.querySelectorAll('[data-days]').forEach(function(b){b.addEventListener('click',function(){var d=parseInt(this.dataset.days),to=new Date(),fr=new Date();fr.setDate(to.getDate()-(d-1));function f(x){return x.toISOString().split('T')[0];}document.getElementById('inp-from').value=f(fr);document.getElementById('inp-to').value=f(to);document.getElementById('filterForm').submit();});});
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
</div>

<div class="ck-pills mb-3">
  <span class="ck-pill"><i class="bi bi-receipt" style="color:#1d4ed8"></i> Payments: <strong><?= number_format(count($rows)) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-cash-stack" style="color:#f97316"></i> Total: <strong>KES <?= number_format($total_amount) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-phone" style="color:#059669"></i> M-Pesa Matched: <strong><?= number_format($mpesa_count) ?></strong></span>
  <span class="ck-pill"><i class="bi bi-calendar-range text-muted"></i> <?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?></span>
</div>

<form id="filterForm" method="GET" action="" class="ck-filter">
  <div class="ck-date-wrap">
    <input type="date" id="inp-from" name="from" value="<?= $from ?>">
    <span class="ck-date-sep">→</span>
    <input type="date" id="inp-to" name="to" value="<?= $to ?>">
  </div>
  <select name="method" class="form-select form-select-sm w-auto">
    <option value="">All Payment Methods</option>
    <?php foreach ($method_options as $key => $label): ?>
      <option value="<?= htmlspecialchars($key) ?>" <?= $method === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
  <div class="ck-presets">
    <button type="button" class="ck-p-btn" data-days="7">7D</button>
    <button type="button" class="ck-p-btn" data-days="30">30D</button>
    <button type="button" class="ck-p-btn" data-days="90">90D</button>
  </div>
  <a href="transactions-report.php" class="btn btn-ck-ghost btn-sm">Reset</a>
</form>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-list-ol"></i></span>Sales Payments</h6>
    <small class="text-muted">Most recent 500</small>
  </div>
  <?php if (empty($rows)): ?>
    <div class="card-body"><div class="ck-empty"><div class="ck-empty-icon"><i class="bi bi-inbox"></i></div><p class="mb-0 fw-semibold">No transactions in this range</p></div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Transaction Code</th>
            <th class="hide-sm">Customer</th>
            <th>Payment Method</th>
            <th class="text-end">Amount</th>
            <th class="hide-sm">M-Pesa Code</th>
            <th>M-Pesa Date</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $method_label = $labels[$r['method']] ?? ucfirst(str_replace('_', ' ', $r['method']));
          $is_mpesa     = !empty($r['mpesa_date']);
        ?>
          <tr>
            <td class="text-muted"><?= date('d M Y, H:i', strtotime($r['sale_date'])) ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($r['invoice_no']) ?></td>
            <td class="hide-sm"><?= htmlspecialchars($r['customer'] ?? 'Walk-in Customer') ?></td>
            <td><span class="ck-chip <?= $is_mpesa ? 'ck-chip-green' : 'ck-chip-slate' ?>"><?= htmlspecialchars($method_label) ?></span></td>
            <td class="text-end fw-bold" style="color:#1d4ed8">KES <?= number_format((float)$r['amount']) ?></td>
            <td class="hide-sm"><?= $r['transaction_no'] ? htmlspecialchars($r['transaction_no']) : '—' ?></td>
            <td><?= $is_mpesa ? date('d M Y, H:i', strtotime($r['mpesa_date'])) : '<span class="text-muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
