<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'CrabKids Dashboard') ?> — CrabKids Kenya</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<header class="ck-topbar">
  <button class="ck-topbar-burger" id="sidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
  </button>

  <a href="/index.php" class="ck-topbar-brand">
    <img src="/assets/images/logo.avif" alt="CrabKids Logo" class="ck-topbar-logo">
    <div>
      <div class="ck-topbar-name">CrabKids Kenya</div>
      <div class="ck-topbar-sub">Stock Dashboard</div>
    </div>
  </a>

  <div class="ck-topbar-sep"></div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="ck-topbar-date d-none d-md-inline"><?= date('D, d M Y') ?></span>
    <span class="ck-live-badge"><span class="ck-live-dot"></span>Live</span>
  </div>
</header>

<!-- ── Mobile overlay ────────────────────────────────────────── -->
<div class="ck-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ───────────────────────────────────────────────── -->
<aside class="ck-sidebar" id="sidebar">
  <div class="ck-nav-section">
    <div class="ck-nav-label">Main</div>

    <a href="/index.php"
       class="ck-nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-speedometer2"></i></span>
      Overview
    </a>

    <a href="/stock-growth.php"
       class="ck-nav-link <?= basename($_SERVER['PHP_SELF']) === 'stock-growth.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
      Stock Trend
    </a>

    <a href="/sales-by-size.php"
       class="ck-nav-link <?= basename($_SERVER['PHP_SELF']) === 'sales-by-size.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-rulers"></i></span>
      Sales by Size
    </a>

    <a href="/low-stock.php"
       class="ck-nav-link <?= basename($_SERVER['PHP_SELF']) === 'low-stock.php' ? 'active' : '' ?>">
      <span class="ck-nav-icon"><i class="bi bi-exclamation-triangle"></i></span>
      Low Stock
      <?php
        try {
          $db_badge   = get_db();
          $badge_stmt = $db_badge->prepare(
            "SELECT COUNT(*) FROM variation_location_details vld
             JOIN product_variations pv ON pv.id = vld.product_variation_id
             JOIN products p ON p.id = vld.product_id
             WHERE p.business_id = ? AND p.is_inactive = 0
               AND vld.qty_available <= p.alert_quantity
               AND p.alert_quantity > 0"
          );
          $badge_stmt->execute([business_id()]);
          $badge_n = (int)$badge_stmt->fetchColumn();
          if ($badge_n > 0): ?>
        <span class="ck-nav-badge"><?= $badge_n ?></span>
      <?php endif; } catch (Throwable $e) { /* silent */ } ?>
    </a>
  </div>

  <div class="ck-sidebar-footer">
    <div class="ck-sidebar-version">CrabKids v3.0</div>
  </div>
</aside>

<!-- ── Main content ───────────────────────────────────────────── -->
<main class="ck-main">
