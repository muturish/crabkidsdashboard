<?php
/**
 * Very simple shared-password gate for the dashboard.
 * Not a replacement for real auth — just keeps casual visitors out.
 * Leave DASHBOARD_PASSWORD blank in .env to disable this entirely.
 */

require_once __DIR__ . '/../config/env.php';

function require_dashboard_login(): void
{
    $configuredPassword = getenv('DASHBOARD_PASSWORD');

    // If no password is configured, skip auth entirely.
    if (!$configuredPassword) {
        return;
    }

    session_start();

    if (!empty($_POST['dashboard_password'])) {
        if (hash_equals($configuredPassword, $_POST['dashboard_password'])) {
            $_SESSION['dashboard_authed'] = true;
        } else {
            $_SESSION['login_error'] = 'Incorrect password.';
        }
        // Avoid resubmission on refresh
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (empty($_SESSION['dashboard_authed'])) {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        include __DIR__ . '/../includes/login.php';
        exit;
    }
}
