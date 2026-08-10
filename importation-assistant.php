<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'Importation Assistant';
$page_subtitle = 'Enter the boxes you plan to order and get a full gender / product line / size breakdown';

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

    warnBadge('badge-gender', ['inp-pct-boys','inp-pct-girls']);
    warnBadge('badge-type', ['inp-pct-wetlook','inp-pct-polish','inp-pct-sport']);
    warnBadge('badge-size', ['inp-pct-s1','inp-pct-s2','inp-pct-s3']);

    var genders = splitTotal(total, genderPct);

    \$('kpi-total').textContent = fmt(total) + ' boxes';

    var grandTotal = 0;
    genders.forEach(function(g){
      var types = splitTotal(g.boxes, typePct);
      var tbody = \$('tbl-' + g.key.toLowerCase());
      var kpiEl = \$('kpi-' + g.key.toLowerCase());
      if (kpiEl) kpiEl.textContent = fmt(g.boxes) + ' boxes';
      if (!tbody) return;
      tbody.innerHTML = '';
      var genderTotal = 0;
      types.forEach(function(t){
        var sizes = splitTotal(t.boxes, sizePct);
        var typeTotal = 0;
        sizes.forEach(function(s, si){
          typeTotal += s.boxes;
          var tr = document.createElement('tr');
          tr.innerHTML =
            (si === 0 ? '<td class="fw-semibold" rowspan="3">' + t.key + '</td>' : '') +
            '<td>' + s.key + '</td>' +
            '<td class="text-end fw-bold" style="color:#1d4ed8">' + fmt(s.boxes) + '</td>';
          tbody.appendChild(tr);
        });
        var subTr = document.createElement('tr');
        subTr.className = 'table-light';
        subTr.innerHTML = '<td colspan="2" class="fw-bold text-end">' + t.key + ' Subtotal</td><td class="text-end fw-bold">' + fmt(typeTotal) + '</td>';
        tbody.appendChild(subTr);
        genderTotal += typeTotal;
      });
      grandTotal += genderTotal;
      var totalEl = \$('total-' + g.key.toLowerCase());
      if (totalEl) totalEl.textContent = fmt(genderTotal) + ' boxes';
    });
  }

  ['inp-total-boxes','inp-pct-boys','inp-pct-girls','inp-pct-wetlook','inp-pct-polish','inp-pct-sport','inp-pct-s1','inp-pct-s2','inp-pct-s3']
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
  </div>
</div>

<!-- Results -->
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-blue"><i class="bi bi-person-fill"></i></span>Boys</h6>
        <span class="ck-chip ck-chip-blue" id="total-boys">0 boxes</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Product Line</th><th>Size</th><th class="text-end">Boxes</th></tr></thead>
          <tbody id="tbl-boys"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-pink"><i class="bi bi-person-dress"></i></span>Girls</h6>
        <span class="ck-chip ck-chip-pink" id="total-girls">0 boxes</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Product Line</th><th>Size</th><th class="text-end">Boxes</th></tr></thead>
          <tbody id="tbl-girls"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
