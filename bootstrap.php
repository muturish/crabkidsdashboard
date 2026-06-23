<?php
session_start();

// Redirect to login if not authenticated
if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
