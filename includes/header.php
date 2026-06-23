<?php
/**
 * Shared layout header. Expects $pageTitle and $activePage to be set
 * by the including page before this file is required.
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> · CrabKids Stock Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white" style="width: 260px; min-height: 100vh;">
        <a href="index.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
            <i class="bi bi-box-seam fs-3 me-2"></i>
            <span class="fs-5 fw-semibold">CrabKids Stock</span>
        </a>
        <hr class="text-secondary">
        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li class="nav-item">
                <a href="index.php" class="nav-link text-white <?= $activePage === 'overview' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Overview
                </a>
            </li>
            <li>
                <a href="stock-growth.php" class="nav-link text-white <?= $activePage === 'growth' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up-arrow me-2"></i> Stock Growth
                </a>
            </li>
            <li>
                <a href="low-stock.php" class="nav-link text-white <?= $activePage === 'low_stock' ? 'active' : '' ?>">
                    <i class="bi bi-exclamation-triangle me-2"></i> Low Stock Alerts
                </a>
            </li>
        </ul>
        <hr class="text-secondary">
        <div class="text-secondary small">
            Read-only view of your POS database.<br>
            Data updates live from <code>crabkidskenyaco_pos</code>.
        </div>
    </nav>

    <!-- Main content -->
    <main class="flex-grow-1 p-4" style="background:#f5f6fa; min-height:100vh;">
