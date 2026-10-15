<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'CrabKids Dashboard') ?> — CrabKids Kenya</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">
</head>
<body>

<!-- TOPBAR -->
<header class="ck-topbar">
  <button class="ck-burger" id="sidebarBtn"><i class="bi bi-list"></i></button>
  <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2">
    <img src="assets/images/logo.svg" alt="CrabKids logo" class="ck-topbar-logo">
    <div>
      <div class="ck-topbar-name">CrabKids Kenya</div>
      <div class="ck-topbar-sub">Stock Dashboard</div>
    </div>
  </a>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="ck-topbar-date d-none d-md-inline"><?= date('D, d M Y') ?></span>
    <span class="ck-live"><span class="ck-live-dot"></span>Live</span>
    <a href="logout.php" class="text-white-50 text-decoration-none" title="Log out" style="font-size:1.1rem;"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</header>

<!-- MOBILE OVERLAY -->
<div class="ck-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="ck-sidebar" id="sidebar">
  <div class="ck-nav-group">
    <div class="ck-nav-label">Main</div>

    <a href="index.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-speedometer2"></i></span>
      Overview
    </a>

    <a href="cumulative-net-change.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'cumulative-net-change.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-graph-up"></i></span>
      Cumulative Net Change
    </a>

    <a href="sales-by-size.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'sales-by-size.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-rulers"></i></span>
      Sales by Size
    </a>

    <a href="best-sellers.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'best-sellers.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-trophy"></i></span>
      Best Sellers
    </a>

    <a href="projected-income.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'projected-income.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
      Projected Income
    </a>

    <a href="targets-monitoring.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'targets-monitoring.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-bullseye"></i></span>
      Targets Monitoring
    </a>

    <a href="importation-assistant.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'importation-assistant.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-box-seam"></i></span>
      Importation Assistant
    </a>

    <a href="transactions-report.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'transactions-report.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-receipt"></i></span>
      Transactions
    </a>

    <a href="call-center-orders.php"
       class="ck-nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['call-center-orders.php', 'call-center-order-new.php', 'call-center-order-view.php']) ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-headset"></i></span>
      Call Center Orders
    </a>

    <a href="low-stock.php"
       class="ck-nav-item <?= basename($_SERVER['PHP_SELF']) === 'low-stock.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-exclamation-triangle"></i></span>
      Low Stock
      <?php try {
        $db_badge   = get_db();
        $badge_stmt = $db_badge->prepare(
          "SELECT COUNT(*) FROM variation_location_details vld
           JOIN product_variations pv ON pv.id = vld.product_variation_id
           JOIN products p ON p.id = vld.product_id
           WHERE p.business_id = ? AND p.is_inactive = 0
             AND vld.qty_available <= p.alert_quantity AND p.alert_quantity > 0"
        );
        $badge_stmt->execute([business_id()]);
        $n = (int)$badge_stmt->fetchColumn();
        if ($n > 0): ?><span class="ck-nav-badge"><?= $n ?></span><?php
        endif;
      } catch (Throwable $e) {} ?>
    </a>
  </div>

  <div class="ck-sidebar-foot">CrabKids v3 · <?= date('Y') ?></div>
</aside>

<!-- MAIN -->
<main class="ck-main">
