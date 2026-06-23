<?php
require_once __DIR__ . '/env.php';

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = getenv('DB_HOST')     ?: 'localhost';
    $port = getenv('DB_PORT')     ?: '3306';
    $name = getenv('DB_DATABASE') ?: 'crabkidskenyaco_pos';
    $user = getenv('DB_USERNAME') ?: 'crabkidskenyaco_pos';
    $pass = getenv('DB_PASSWORD') ?: 'XBGHQE[dbMCyRKp8';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function business_id(): int
{
    return (int)(getenv('BUSINESS_ID') ?: 1);
}
