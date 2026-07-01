/* Projected Income Growth — client-side forecasting engine.
   All math happens here so KPI cards, chart, table and scenarios
   recalculate instantly as the user edits assumptions. */
(function () {
  'use strict';

  var els = {};
  var chart = null;
  var lastRows = [];   // rows for the currently-selected scenario (used by exports)
  var fyStart = new Date().getFullYear();

  function $(id) { return document.getElementById(id); }

  function fmtKES(n) {
    n = Math.round(n || 0);
    return 'KES ' + n.toLocaleString('en-US');
  }
  function fmtPct(n) {
    n = n || 0;
    return (n >= 0 ? '+' : '') + n.toFixed(1) + '%';
  }
  function num(id, fallback) {
    var v = parseFloat($(id).value);
    return isNaN(v) ? fallback : v;
  }

  /* Compound growth with optional flat annual add-on and inflation adjustment. */
  function computeYears(current, growthPct, additional, inflationPct, years) {
    var g = growthPct / 100, infl = inflationPct / 100;
    var rows = [];
    var prev = current;
    var cumulative = 0;
    for (var y = 1; y <= years; y++) {
      var nominal  = prev * (1 + g) + additional;
      var increase = nominal - prev;
      var growthYoY = prev !== 0 ? (increase / prev * 100) : 0;
      cumulative += nominal;
      var real = infl > 0 ? nominal / Math.pow(1 + infl, y) : nominal;
      rows.push({
        year: y,
        fyLabel: 'FY ' + (fyStart + y),
        nominal: nominal,
        increase: increase,
        growthYoY: growthYoY,
        cumulative: cumulative,
        real: real
      });
      prev = nominal;
    }
    return rows;
  }

  function cagr(current, finalVal, years) {
    if (current <= 0 || years <= 0) return 0;
    return (Math.pow(finalVal / current, 1 / years) - 1) * 100;
  }

  function trendBadge(delta) {
    if (delta > 0.5) return '<span class="ck-trend up"><i class="bi bi-arrow-up-right"></i> growing</span>';
    if (delta < -0.5) return '<span class="ck-trend down"><i class="bi bi-arrow-down-right"></i> declining</span>';
    return '<span class="ck-trend flat"><i class="bi bi-dash"></i> flat</span>';
  }

  function readInputs() {
    var fySel = $('sel-fy');
    fyStart = fySel ? (parseInt(fySel.value, 10) || new Date().getFullYear()) : new Date().getFullYear();
    var current     = num('inp-current-income', 0);
    var growth      = num('inp-growth-rate', 0);
    var additional  = num('inp-additional-income', 0);
    var inflation   = num('inp-inflation-rate', 0);
    var period      = Math.min(10, Math.max(1, Math.round(num('inp-period', 3))));
    $('inp-period').value = period;
    return { current: current, growth: growth, additional: additional, inflation: inflation, period: period };
  }

  function renderKpis(current, rows) {
    var y1 = rows[0], y2 = rows[1], y3 = rows[2];
    var last = rows[rows.length - 1];

    $('kpi-current-val').textContent = fmtKES(current);

    $('kpi-y1-val').textContent = y1 ? fmtKES(y1.nominal) : '—';
    $('kpi-y1-trend').innerHTML = y1 ? trendBadge(y1.growthYoY) + ' ' + fmtPct(y1.growthYoY) : '';

    $('kpi-y2-val').textContent = y2 ? fmtKES(y2.nominal) : '—';
    $('kpi-y2-trend').innerHTML = y2 ? trendBadge(y2.growthYoY) + ' ' + fmtPct(y2.growthYoY) : '';

    $('kpi-y3-val').textContent = y3 ? fmtKES(y3.nominal) : '—';
    $('kpi-y3-trend').innerHTML = y3 ? trendBadge(y3.growthYoY) + ' ' + fmtPct(y3.growthYoY) : '';

    var totalGrowthPct = current > 0 ? ((last.nominal - current) / current * 100) : 0;
    $('kpi-growth-val').textContent = fmtPct(totalGrowthPct);
    $('kpi-growth-trend').innerHTML = trendBadge(totalGrowthPct) + ' over ' + rows.length + ' yr' + (rows.length > 1 ? 's' : '');

    var cagrVal = cagr(current, last.nominal, rows.length);
    $('kpi-cagr-val').textContent = cagrVal.toFixed(1) + '%';
    $('kpi-cagr-trend').innerHTML = trendBadge(cagrVal) + ' CAGR';
  }

  function renderChart(current, rows, inflation) {
    var labels = ['Current'].concat(rows.map(function (r) { return r.fyLabel; }));
    var nominalData = [current].concat(rows.map(function (r) { return Math.round(r.nominal); }));
    var datasets = [{
      label: 'Projected Income (Nominal)',
      data: nominalData,
      borderColor: '#1d4ed8',
      backgroundColor: 'rgba(29,78,216,.1)',
      fill: true,
      tension: 0.35,
      pointRadius: 4,
      pointHoverRadius: 6
    }];
    if (inflation > 0) {
      datasets.push({
        label: 'Inflation-Adjusted (Real)',
        data: [current].concat(rows.map(function (r) { return Math.round(r.real); })),
        borderColor: '#f97316',
        backgroundColor: 'rgba(249,115,22,.08)',
        borderDash: [6, 4],
        fill: false,
        tension: 0.35,
        pointRadius: 3
      });
    }

    var ctx = $('projChart');
    if (!ctx) return;
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
      type: 'line',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: function (c) { return c.dataset.label + ': ' + fmtKES(c.parsed.y); }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: false, ticks: { callback: function (v) { return 'KES ' + (v / 1000).toFixed(0) + 'k'; } } }
        }
      }
    });
  }

  function renderTable(rows) {
    var body = $('proj-table-body');
    body.innerHTML = '';
    rows.forEach(function (r) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="fw-semibold">' + r.fyLabel + '</td>' +
        '<td class="text-end fw-bold" style="color:#1d4ed8">' + fmtKES(r.nominal) + '</td>' +
        '<td class="text-end">' + fmtKES(r.increase) + '</td>' +
        '<td class="text-end">' + fmtPct(r.growthYoY) + '</td>' +
        '<td class="text-end">' + fmtKES(r.cumulative) + '</td>';
      body.appendChild(tr);
    });
    var last = rows[rows.length - 1];
    var totalIncrease = rows.reduce(function (s, r) { return s + r.increase; }, 0);
    $('tf-total-income').textContent = fmtKES(last.nominal);
    $('tf-total-increase').textContent = fmtKES(totalIncrease);
    $('tf-total-cumulative').textContent = fmtKES(last.cumulative);
  }

  function renderScenarios(current, additional, inflation, period) {
    var scenarios = [
      { key: 'conservative', rate: 10 },
      { key: 'expected',     rate: 20 },
      { key: 'optimistic',   rate: 40 }
    ];
    scenarios.forEach(function (s) {
      var rows = computeYears(current, s.rate, additional, inflation, period);
      var last = rows[rows.length - 1];
      var totalGrowthPct = current > 0 ? ((last.nominal - current) / current * 100) : 0;
      $('scn-' + s.key + '-y1').textContent = rows[0] ? fmtKES(rows[0].nominal) : '—';
      $('scn-' + s.key + '-y2').textContent = rows[1] ? fmtKES(rows[1].nominal) : '—';
      $('scn-' + s.key + '-y3').textContent = rows[2] ? fmtKES(rows[2].nominal) : '—';
      $('scn-' + s.key + '-total').textContent = fmtKES(last.cumulative);
      $('scn-' + s.key + '-growth').textContent = fmtPct(totalGrowthPct);
    });
  }

  function renderInsights(current, rows, inflation) {
    var last = rows[rows.length - 1];
    var totalIncrease = last.nominal - current;
    var totalGrowthPct = current > 0 ? (totalIncrease / current * 100) : 0;
    var avgGrowth = rows.reduce(function (s, r) { return s + r.growthYoY; }, 0) / rows.length;
    var cagrVal = cagr(current, last.nominal, rows.length);

    var best = rows[0];
    rows.forEach(function (r) { if (r.increase > best.increase) best = r; });

    var items = [
      'Projected revenue is expected to reach <strong>' + fmtKES(last.nominal) + '</strong> by <strong>' + last.fyLabel + '</strong>, a total increase of ' + fmtKES(totalIncrease) + ' (' + fmtPct(totalGrowthPct) + ').',
      'Average year-over-year growth across the projection period is <strong>' + avgGrowth.toFixed(1) + '%</strong>.',
      'Compound Annual Growth Rate (CAGR): <strong>' + cagrVal.toFixed(1) + '%</strong>.',
      '<strong>' + best.fyLabel + '</strong> shows the strongest projected increase, adding ' + fmtKES(best.increase) + '.'
    ];
    if (inflation > 0) {
      items.push('After adjusting for ' + inflation.toFixed(1) + '% inflation, real-terms revenue in ' + last.fyLabel + ' is approximately ' + fmtKES(last.real) + '.');
    }

    var list = $('insights-list');
    list.innerHTML = items.map(function (t) {
      return '<li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>' + t + '</li>';
    }).join('');

    var rec, tone;
    if (cagrVal >= 25) { rec = 'Aggressive growth trajectory — consider scaling inventory, staffing and store capacity to sustain demand.'; tone = 'ok'; }
    else if (cagrVal >= 15) { rec = 'Healthy, sustainable growth — maintain the current strategy while monitoring margins closely.'; tone = 'ok'; }
    else if (cagrVal >= 5) { rec = 'Moderate growth — consider new revenue streams, marketing investment or expanded product lines to accelerate growth.'; tone = 'warn'; }
    else { rec = 'Growth is flat or below typical inflation — a strategic review of pricing, product mix and marketing is recommended.'; tone = 'warn'; }

    var box = $('recommendation-box');
    box.className = 'ck-alert ' + tone + ' mb-0';
    box.innerHTML = '<i class="bi bi-lightbulb-fill"></i><div><strong>Recommendation:</strong> ' + rec + '<br><small class="opacity-75">Automated insight based on the assumptions entered above — not financial advice.</small></div>';
  }

  function render() {
    var inp = readInputs();
    var rows = computeYears(inp.current, inp.growth, inp.additional, inp.inflation, inp.period);
    lastRows = rows;

    renderKpis(inp.current, rows);
    renderChart(inp.current, rows, inp.inflation);
    renderTable(rows);
    renderScenarios(inp.current, inp.additional, inp.inflation, inp.period);
    renderInsights(inp.current, rows, inp.inflation);
  }

  function exportPDF() {
    var el = $('ck-report-area');
    if (!el || !window.html2canvas || !window.jspdf) return;
    html2canvas(el, { scale: 2, backgroundColor: '#ffffff' }).then(function (canvas) {
      var imgData = canvas.toDataURL('image/png');
      var pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
      var pageWidth  = pdf.internal.pageSize.getWidth();
      var pageHeight = pdf.internal.pageSize.getHeight();
      var imgWidth   = pageWidth;
      var imgHeight  = canvas.height * imgWidth / canvas.width;
      var heightLeft = imgHeight;
      var position   = 0;

      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;
      while (heightLeft > 0) {
        position = heightLeft - imgHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }
      pdf.save('Projected-Income-Growth.pdf');
    });
  }

  function exportExcel() {
    if (!window.XLSX || !lastRows.length) return;
    var wsData = [['Financial Year', 'Projected Income (KES)', 'Annual Increase (KES)', 'Growth %', 'Cumulative Revenue (KES)']];
    lastRows.forEach(function (r) {
      wsData.push([r.fyLabel, Math.round(r.nominal), Math.round(r.increase), r.growthYoY.toFixed(1) + '%', Math.round(r.cumulative)]);
    });
    var ws = XLSX.utils.aoa_to_sheet(wsData);
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Projection');
    XLSX.writeFile(wb, 'Projected-Income-Growth.xlsx');
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('inp-current-income')) return; // not on this page

    ['inp-current-income', 'inp-growth-rate', 'inp-additional-income', 'inp-inflation-rate', 'inp-period', 'sel-fy']
      .forEach(function (id) {
        var el = $(id);
        if (el) el.addEventListener('input', render);
      });

    var calcBtn = $('btn-calculate');
    if (calcBtn) calcBtn.addEventListener('click', render);

    var printBtn = $('btn-print');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

    var pdfBtn = $('btn-export-pdf');
    if (pdfBtn) pdfBtn.addEventListener('click', exportPDF);

    var xlsBtn = $('btn-export-excel');
    if (xlsBtn) xlsBtn.addEventListener('click', exportExcel);

    render();
  });
})();
