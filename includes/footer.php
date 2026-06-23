</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
/* ── Chart.js global theme ────────────────────────────────── */
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#64748b';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth      = 8;
Chart.defaults.plugins.legend.labels.boxHeight     = 8;
Chart.defaults.plugins.legend.labels.padding       = 16;
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
Chart.defaults.plugins.tooltip.titleColor      = '#f1f5f9';
Chart.defaults.plugins.tooltip.bodyColor       = '#94a3b8';
Chart.defaults.plugins.tooltip.borderColor     = '#1e293b';
Chart.defaults.plugins.tooltip.borderWidth     = 1;
Chart.defaults.plugins.tooltip.padding         = 10;
Chart.defaults.plugins.tooltip.cornerRadius    = 8;
Chart.defaults.plugins.tooltip.titleFont       = { size: 12, weight: '700' };
Chart.defaults.scale.grid.color                = '#f1f5f9';
Chart.defaults.scale.grid.drawBorder           = false;
Chart.defaults.scale.ticks.padding             = 8;
Chart.defaults.elements.line.tension           = 0.35;
Chart.defaults.elements.line.borderWidth       = 2.5;
Chart.defaults.elements.point.radius           = 3;
Chart.defaults.elements.point.hoverRadius      = 6;
Chart.defaults.elements.bar.borderRadius       = 4;
</script>

<!-- ── Sidebar toggle ────────────────────────────────────────── -->
<script>
(function(){
  var btn     = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  function open()  { sidebar.classList.add('is-open');    overlay.classList.add('is-visible'); }
  function close() { sidebar.classList.remove('is-open'); overlay.classList.remove('is-visible'); }
  if (btn)     btn.addEventListener('click', function(){ sidebar.classList.contains('is-open') ? close() : open(); });
  if (overlay) overlay.addEventListener('click', close);
  window.addEventListener('resize', function(){ if (window.innerWidth >= 1024) close(); });
})();
</script>

<?php if (!empty($inline_scripts)): ?>
<script><?= $inline_scripts ?></script>
<?php endif; ?>

</body>
</html>
