<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Importation Assistant';
$page_subtitle = 'Enter the boxes you plan to order and get a full gender / product line / size breakdown, plus a landed cost estimate';

$inline_scripts = <<<JS
(function(){
  'use strict';

  function \$(id){ return document.getElementById(id); }

  // Split `total` whole units across `parts` ([{key,pct}]) so the rounded
  // results always sum back to exactly `total` (largest-remainder method).
  function splitTotal(total, parts){
    var sumPct = parts.reduce(function(s,p){return s+(parseFloat(p.pct)||0);},0);
    if (sumPct <= 0) return parts.map(function(p){return {key:p.key, boxes:0, exact:0};});
    var exact = parts.map(function(p){ return { key:p.key, exact: total * (parseFloat(p.pct)||0) / sumPct }; });
    var floored = exact.map(function(e){ return { key:e.key, exact:e.exact, boxes: Math.floor(e.exact) }; });
    var used = floored.reduce(function(s,f){return s+f.boxes;},0);
    var remainder = Math.round(total - used);
    var order = floored.slice().sort(function(a,b){ return (b.exact - b.boxes) - (a.exact - a.boxes); });
    for (var i=0; i<remainder; i++) order[i % order.length].boxes += 1;
    return floored;
  }

  function pctSum(ids){
    return ids.reduce(function(s,id){ var el=\$(id); return s + (el ? (parseFloat(el.value)||0) : 0); }, 0);
  }

  function warnBadge(id, ids){
    var sum = pctSum(ids);
    var el = \$(id);
    if (!el) return;
    var ok = Math.abs(sum - 100) < 0.05;
    el.textContent = sum.toFixed(1) + '% of 100%';
    el.className = 'badge ' + (ok ? 'text-bg-success' : 'text-bg-danger');
  }

  function fmt(n){ return Math.round(n).toLocaleString(); }
  function fmt2(n){ return n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

  var SIZE_KEYS = ['25-30', '31-36', '37-42'];

  function render(){
    var total = parseFloat(\$('inp-total-boxes').value) || 0;

    var genderPct = [
      { key:'Boys',  pct: \$('inp-pct-boys').value },
      { key:'Girls', pct: \$('inp-pct-girls').value }
    ];
    var typePct = [
      { key:'Wet Look', pct: \$('inp-pct-wetlook').value },
      { key:'Polish',   pct: \$('inp-pct-polish').value },
      { key:'Sport',    pct: \$('inp-pct-sport').value }
    ];
    var sizePct = [
      { key:'25-30', pct: \$('inp-pct-s1').value },
      { key:'31-36', pct: \$('inp-pct-s2').value },
      { key:'37-42', pct: \$('inp-pct-s3').value }
    ];

    var costPerBox = {
      '25-30': parseFloat(\$('inp-cost-s1').value) || 0,
      '31-36': parseFloat(\$('inp-cost-s2').value) || 0,
      '37-42': parseFloat(\$('inp-cost-s3').value) || 0
    };
    var pairsPerBox = parseFloat(\$('inp-pairs-per-box').value) || 0;
    var yuanPerUsd  = parseFloat(\$('inp-fx-yuan').value) || 1;
    var kesPerUsd   = parseFloat(\$('inp-fx-kes').value) || 1;

    function yuanToKes(yuan){ return (yuan / yuanPerUsd) * kesPerUsd; }
    function fmtY(n){ return '¥' + fmt(n); }
    function fmtK(n){ return 'KES ' + fmt(n); }

    warnBadge('badge-gender', ['inp-pct-boys','inp-pct-girls']);
    warnBadge('badge-type', ['inp-pct-wetlook','inp-pct-polish','inp-pct-sport']);
    warnBadge('badge-size', ['inp-pct-s1','inp-pct-s2','inp-pct-s3']);

    var genders = splitTotal(total, genderPct);

    \$('kpi-total').textContent = fmt(total) + ' boxes';

    var grandTotal = 0;
    var sizeBoxTotals = { '25-30':0, '31-36':0, '37-42':0 };

    genders.forEach(function(g){
      var types = splitTotal(g.boxes, typePct);
      var tbody = \$('tbl-' + g.key.toLowerCase());
      var kpiEl = \$('kpi-' + g.key.toLowerCase());
      if (kpiEl) kpiEl.textContent = fmt(g.boxes) + ' boxes';
      if (!tbody) return;
      tbody.innerHTML = '';
      var genderTotal = 0;
      var genderCostYuan = 0;
      types.forEach(function(t){
        var sizes = splitTotal(t.boxes, sizePct);
        var typeTotal = 0;
        var typeCostYuan = 0;
        sizes.forEach(function(s, si){
          typeTotal += s.boxes;
          sizeBoxTotals[s.key] += s.boxes;
          var lineCostYuan = s.boxes * (costPerBox[s.key] || 0);
          typeCostYuan += lineCostYuan;
          var tr = document.createElement('tr');
          tr.innerHTML =
            (si === 0 ? '<td class="fw-semibold" rowspan="3">' + t.key + '</td>' : '') +
            '<td>' + s.key + '</td>' +
            '<td class="text-end fw-bold" style="color:#1d4ed8">' + fmt(s.boxes) + '</td>' +
            '<td class="text-end text-muted hide-sm">' + fmtK(yuanToKes(lineCostYuan)) + '</td>';
          tbody.appendChild(tr);
        });
        var subTr = document.createElement('tr');
        subTr.className = 'table-light';
        subTr.innerHTML = '<td colspan="2" class="fw-bold text-end">' + t.key + ' Subtotal</td><td class="text-end fw-bold">' + fmt(typeTotal) + '</td><td class="text-end fw-bold hide-sm">' + fmtK(yuanToKes(typeCostYuan)) + '</td>';
        tbody.appendChild(subTr);
        genderTotal += typeTotal;
        genderCostYuan += typeCostYuan;
      });
      grandTotal += genderTotal;
      var totalEl = \$('total-' + g.key.toLowerCase());
      if (totalEl) totalEl.textContent = fmt(genderTotal) + ' boxes';
      var costEl = \$('cost-' + g.key.toLowerCase());
      if (costEl) costEl.textContent = fmtK(yuanToKes(genderCostYuan));
    });

    // Cost estimate summary, by size (cost only depends on size, not gender/type)
    var totalCostYuan = 0, totalPairs = 0;
    var costBody = \$('tbl-cost');
    if (costBody) {
      costBody.innerHTML = '';
      SIZE_KEYS.forEach(function(key){
        var boxes = sizeBoxTotals[key];
        var pairs = boxes * pairsPerBox;
        var costYuan = boxes * costPerBox[key];
        var costUsd  = costYuan / yuanPerUsd;
        var costKes  = costUsd * kesPerUsd;
        totalCostYuan += costYuan;
        totalPairs    += pairs;
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="fw-semibold">' + key + '</td>' +
          '<td class="text-end">' + fmt(boxes) + '</td>' +
          '<td class="text-end hide-sm">' + fmt(pairs) + '</td>' +
          '<td class="text-end hide-sm">' + fmtY(costPerBox[key]) + '</td>' +
          '<td class="text-end">' + fmtY(costYuan) + '</td>' +
          '<td class="text-end hide-sm">$' + fmt2(costUsd) + '</td>' +
          '<td class="text-end fw-bold" style="color:#059669">' + fmtK(costKes) + '</td>';
        costBody.appendChild(tr);
      });
    }

    var totalCostUsd = totalCostYuan / yuanPerUsd;
    var totalCostKes = totalCostUsd * kesPerUsd;

    \$('kpi-total-pairs').textContent = fmt(totalPairs) + ' pairs';
    \$('kpi-cost-yuan').textContent = fmtY(totalCostYuan);
    \$('kpi-cost-usd').textContent  = '$' + fmt2(totalCostUsd);
    \$('kpi-cost-kes').textContent  = fmtK(totalCostKes);
    \$('tf-cost-boxes').textContent = fmt(grandTotal);
    \$('tf-cost-pairs').textContent = fmt(totalPairs);
    \$('tf-cost-yuan').textContent  = fmtY(totalCostYuan);
    \$('tf-cost-usd').textContent   = '$' + fmt2(totalCostUsd);
    \$('tf-cost-kes').textContent   = fmtK(totalCostKes);

    var perPairKes = totalPairs > 0 ? totalCostKes / totalPairs : 0;
    \$('cost-per-pair').textContent = perPairKes > 0 ? (fmtK(perPairKes) + ' average landed cost per pair') : '';
  }

  ['inp-total-boxes','inp-pct-boys','inp-pct-girls','inp-pct-wetlook','inp-pct-polish','inp-pct-sport','inp-pct-s1','inp-pct-s2','inp-pct-s3',
   'inp-cost-s1','inp-cost-s2','inp-cost-s3','inp-pairs-per-box','inp-fx-yuan','inp-fx-kes']
    .forEach(function(id){ var el = \$(id); if (el) el.addEventListener('input', render); });

  var printBtn = \$('btn-print');
  if (printBtn) printBtn.addEventListener('click', function(){ window.print(); });

  render();
})();
JS;

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
  <div class="ck-no-print">
    <button type="button" id="btn-print" class="btn btn-ck-ghost btn-sm"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<div class="ck-pills mb-3">
  <span class="ck-pill"><i class="bi bi-box-seam" style="color:#1d4ed8"></i> Total: <strong id="kpi-total">0 boxes</strong></span>
  <span class="ck-pill"><i class="bi bi-circle-fill" style="color:#1d4ed8;font-size:.5rem;"></i> Boys: <strong id="kpi-boys">0 boxes</strong></span>
  <span class="ck-pill"><i class="bi bi-circle-fill" style="color:#db2777;font-size:.5rem;"></i> Girls: <strong id="kpi-girls">0 boxes</strong></span>
  <span class="ck-pill"><i class="bi bi-bag" style="color:#f97316"></i> Pairs: <strong id="kpi-total-pairs">0 pairs</strong></span>
  <span class="ck-pill"><i class="bi bi-currency-exchange" style="color:#059669"></i> Landed Cost: <strong id="kpi-cost-kes">KES 0</strong> <span class="text-muted">(<span id="kpi-cost-yuan">¥0</span> · <span id="kpi-cost-usd">$0</span>)</span></span>
</div>

<!-- Inputs -->
<div class="card mb-4 ck-no-print">
  <div class="card-header d-flex align-items-center gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-sliders"></i></span>Order &amp; Split Assumptions</h6>
    <small class="text-muted">Recalculates instantly as you type — percentages are editable</small>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <label class="form-label ck-kpi-label">Total Boxes to Order</label>
        <input type="number" id="inp-total-boxes" class="form-control form-control-sm" min="0" step="1" value="100">
      </div>
    </div>

    <div class="row g-3 align-items-center mb-3">
      <div class="col-12 col-md-2"><span class="ck-kpi-label">Gender Split</span></div>
      <div class="col-6 col-md-3">
        <label class="form-label ck-kpi-label">Boys %</label>
        <input type="number" id="inp-pct-boys" class="form-control form-control-sm" min="0" max="100" step="1" value="40">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label ck-kpi-label">Girls %</label>
        <input type="number" id="inp-pct-girls" class="form-control form-control-sm" min="0" max="100" step="1" value="60">
      </div>
      <div class="col-12 col-md-4"><span id="badge-gender" class="badge text-bg-success">100.0% of 100%</span></div>
    </div>

    <div class="row g-3 align-items-center mb-3">
      <div class="col-12 col-md-2"><span class="ck-kpi-label">Product Line Split</span><br><small class="text-muted">applies to both Boys &amp; Girls</small></div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">Wet Look %</label>
        <input type="number" id="inp-pct-wetlook" class="form-control form-control-sm" min="0" max="100" step="1" value="50">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">Polish %</label>
        <input type="number" id="inp-pct-polish" class="form-control form-control-sm" min="0" max="100" step="1" value="40">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">Sport %</label>
        <input type="number" id="inp-pct-sport" class="form-control form-control-sm" min="0" max="100" step="1" value="10">
      </div>
      <div class="col-12 col-md-4"><span id="badge-type" class="badge text-bg-success">100.0% of 100%</span></div>
    </div>

    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-2"><span class="ck-kpi-label">Size Split</span><br><small class="text-muted">applies to every line above</small></div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">25-30 %</label>
        <input type="number" id="inp-pct-s1" class="form-control form-control-sm" min="0" max="100" step="1" value="40">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">31-36 %</label>
        <input type="number" id="inp-pct-s2" class="form-control form-control-sm" min="0" max="100" step="1" value="40">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">37-42 %</label>
        <input type="number" id="inp-pct-s3" class="form-control form-control-sm" min="0" max="100" step="1" value="20">
      </div>
      <div class="col-12 col-md-4"><span id="badge-size" class="badge text-bg-success">100.0% of 100%</span></div>
    </div>

    <hr class="my-4">

    <div class="row g-3 align-items-center mb-3">
      <div class="col-12 col-md-2"><span class="ck-kpi-label">Cost per Box (¥)</span><br><small class="text-muted">by size, supplier price</small></div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">25-30</label>
        <input type="number" id="inp-cost-s1" class="form-control form-control-sm" min="0" step="0.01" value="882.00">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">31-36</label>
        <input type="number" id="inp-cost-s2" class="form-control form-control-sm" min="0" step="0.01" value="1005">
      </div>
      <div class="col-4 col-md-2">
        <label class="form-label ck-kpi-label">37-42</label>
        <input type="number" id="inp-cost-s3" class="form-control form-control-sm" min="0" step="0.01" value="1128">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label ck-kpi-label">Pairs / Box</label>
        <input type="number" id="inp-pairs-per-box" class="form-control form-control-sm" min="1" step="1" value="12">
      </div>
    </div>

    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-2"><span class="ck-kpi-label">Exchange Rates</span></div>
      <div class="col-6 col-md-3">
        <label class="form-label ck-kpi-label">¥ per $1</label>
        <input type="number" id="inp-fx-yuan" class="form-control form-control-sm" min="0.01" step="0.01" value="6.73">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label ck-kpi-label">KES per $1</label>
        <input type="number" id="inp-fx-kes" class="form-control form-control-sm" min="0.01" step="0.01" value="130">
      </div>
    </div>
  </div>
</div>

<!-- Results -->
<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-person-fill"></i></span>Boys</h6>
        <div class="d-flex align-items-center gap-2">
          <span class="ck-chip ck-chip-blue" id="total-boys">0 boxes</span>
          <small class="text-muted" id="cost-boys">KES 0</small>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Product Line</th><th>Size</th><th class="text-end">Boxes</th><th class="text-end hide-sm">Cost (KES)</th></tr></thead>
          <tbody id="tbl-boys"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-pink"><i class="bi bi-person-dress"></i></span>Girls</h6>
        <div class="d-flex align-items-center gap-2">
          <span class="ck-chip ck-chip-pink" id="total-girls">0 boxes</span>
          <small class="text-muted" id="cost-girls">KES 0</small>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Product Line</th><th>Size</th><th class="text-end">Boxes</th><th class="text-end hide-sm">Cost (KES)</th></tr></thead>
          <tbody id="tbl-girls"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Cost estimate -->
<div class="d-flex align-items-center justify-content-between mb-3"><span class="ck-label">Landed Cost Estimate</span><span class="ck-chip ck-chip-green">by size, independent of gender/line</span></div>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-green"><i class="bi bi-currency-exchange"></i></span>Cost by Size</h6>
    <small class="text-muted" id="cost-per-pair"></small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Size</th>
          <th class="text-end">Boxes</th>
          <th class="text-end hide-sm">Pairs</th>
          <th class="text-end hide-sm">Cost/Box (¥)</th>
          <th class="text-end">Total (¥)</th>
          <th class="text-end hide-sm">Total ($)</th>
          <th class="text-end">Total (KES)</th>
        </tr>
      </thead>
      <tbody id="tbl-cost"></tbody>
      <tfoot>
        <tr class="fw-bold">
          <td>Total</td>
          <td class="text-end" id="tf-cost-boxes">0</td>
          <td class="text-end hide-sm" id="tf-cost-pairs">0</td>
          <td class="text-end hide-sm">—</td>
          <td class="text-end" id="tf-cost-yuan">¥0</td>
          <td class="text-end hide-sm" id="tf-cost-usd">$0</td>
          <td class="text-end" style="color:#059669" id="tf-cost-kes">KES 0</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
