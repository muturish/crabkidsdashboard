</main><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
// Sidebar toggle (mobile)
const sidebarToggle  = document.getElementById('sidebarToggle');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar()  { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); }
function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); }

sidebarToggle?.addEventListener('click', openSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);
</script>

<?php if (!empty($inline_scripts)): ?>
<script><?= $inline_scripts ?></script>
<?php endif; ?>

</body>
</html>
