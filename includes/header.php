<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'CrabKids Dashboard') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

<!-- ── Topbar ──────────────────────────────────────────────── -->
<nav class="topbar d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-link text-white p-0 d-lg-none" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <img src="/assets/images/logo.avif" alt="CrabKids Logo" class="topbar-logo">
        <div class="d-none d-sm-block">
            <span class="topbar-brand">CrabKids Kenya</span>
            <span class="topbar-sub d-none d-md-inline">Stock Dashboard</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-clock me-1"></i><?= date('D, d M Y') ?>
        </span>
        <div class="status-dot" title="Live data"></div>
    </div>
</nav>

<!-- ── Mobile sidebar overlay ──────────────────────────────── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo-wrap text-center py-3 d-lg-none">
        <img src="/assets/images/logo.avif" alt="Logo" style="width:56px;border-radius:50%;border:2px solid var(--ck-orange);">
    </div>
    <div class="sidebar-header">
        <span class="sidebar-label">Navigation</span>
    </div>
    <nav class="sidebar-nav">
        <a href="/index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i><span>Overview</span>
        </a>
        <a href="/stock-growth.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'stock-growth.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i><span>Stock Trend</span>
        </a>
        <a href="/low-stock.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'low-stock.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i><span>Low Stock</span>
        </a>
    </nav>
</aside>

<!-- ── Main content ─────────────────────────────────────────── -->
<main class="main-content">
    <div class="page-header mb-4 d-flex align-items-start justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
            <?php if (!empty($page_subtitle)): ?>
                <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($page_actions)) echo $page_actions; ?>
    </div>
