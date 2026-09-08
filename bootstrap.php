<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_login();
