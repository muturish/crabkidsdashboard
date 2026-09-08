<?php
function require_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    if (empty($_SESSION['authenticated'])) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '/index.php';
        header('Location: /login.php?redirect=' . urlencode($redirect));
        exit;
    }
}
