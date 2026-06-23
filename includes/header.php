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

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<nav class="topbar d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-link text-white p-0 sidebar-toggle d-lg-none" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <img src="/assets/images/logo.avif" alt="CrabKids Logo" class="topbar-logo">
        <span class="topbar-brand d-none d-sm-inline">CrabKids Kenya</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?>
        </span>
        <div class="dropdown">
            <button class="btn btn-link text-white p-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="avatar"><i class="bi bi-person-fill"></i></div>
                <span class="small d-none d-md-inline"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Sidebar overlay (mobile) ──────────────────────────────── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ───────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <span class="sidebar-label">MAIN MENU</span>
    </div>
    <nav class="sidebar-nav">
        <a href="/index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i><span>Overview</span>
        </a>
        <a href="/stock-growth.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'stock-growth.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i><span>Stock Growth</span>
        </a>
        <a href="/low-stock.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'low-stock.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i><span>Low Stock Alerts</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/logout.php" class="sidebar-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i><span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- ── Main content ───────────────────────────────────────────── -->
<main class="main-content">
    <div class="page-header mb-4">
        <h1 class="page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        <?php if (!empty($page_subtitle)): ?>
            <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
        <?php endif; ?>
    </div>
