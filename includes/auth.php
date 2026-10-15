<?php
function require_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    if (empty($_SESSION['authenticated'])) {
        // api/ endpoints are hit via fetch() -- a redirect to a login page
        // is useless there (and the relative URL would point at api/login.php).
        if (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'api') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Session expired - please log in again.']);
            exit;
        }

        $redirect = $_SERVER['REQUEST_URI'] ?? '/index.php';
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit;
    }
}
