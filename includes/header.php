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
<header class="topbar">
  <button class="topbar-hamburger" id="sidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
  </button>

  <a href="/index.php" class="topbar-brand">
    <img src="/assets/images/logo.avif" alt="CrabKids Logo" class="topbar-logo">
    <div class="topbar-brand-text">
      <span class="topbar-brand-name">CrabKids Kenya</span>
      <span class="topbar-brand-sub">STOCK DASHBOARD</span>
    </div>
  </a>

  <div class="topbar-divider"></div>
  <div class="topbar-spacer"></div>

  <div class="topbar-meta">
    <span class="topbar-date"><?= date('D, d M Y') ?></span>
    <span class="topbar-live"><span class="live-dot"></span>Live</span>
  </div>
</header>

<!-- ── Sidebar overlay ────────────────────────────────────────── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ───────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-section">
    <div class="sidebar-section-label">Main</div>

    <a href="/index.php"
       class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'is-active' : '' ?>">
      <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
      Overview
    </a>

    <a href="/stock-growth.php"
       class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'stock-growth.php' ? 'is-active' : '' ?>">
      <span class="nav-icon"><i class="bi bi-graph-up-arrow"></i></span>
      Stock Trend
    </a>

    <a href="/sales-by-size.php"
       class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'sales-by-size.php' ? 'is-active' : '' ?>">
      <span class="nav-icon"><i class="bi bi-rulers"></i></span>
      Sales by Size
    </a>

    <a href="/low-stock.php"
       class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'low-stock.php' ? 'is-active' : '' ?>">
      <span class="nav-icon"><i class="bi bi-exclamation-triangle"></i></span>
      Low Stock
      <?php
        // Show badge on nav item — only compute if we have a DB connection
        try {
          $db_badge = get_db();
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
          if ($badge_n > 0):
      ?>
        <span class="nav-badge"><?= $badge_n ?></span>
      <?php endif; } catch (Throwable $e) { /* silent */ } ?>
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-version">CrabKids v2.0</div>
  </div>
</aside>

<!-- ── Main content ───────────────────────────────────────────── -->
<main class="main-content">
