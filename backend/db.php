<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

$host = trim((string) app_env('DB_HOST', '127.0.0.1'));
$port = filter_var(app_env('DB_PORT', '3306'), FILTER_VALIDATE_INT);
$dbname = trim((string) app_env('DB_NAME', 'dental_booking'));
$username = trim((string) app_env('DB_USER', ''));
$password = (string) app_env('DB_PASSWORD', '');

if ($host === '' || $dbname === '' || $username === '' || $port === false || $port < 1 || $port > 65535) {
    error_log('Database configuration is incomplete or invalid.');
    http_response_code(503);
    if (PHP_SAPI !== 'cli' && str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/backend/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Usluga trenutno nije dostupna.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Usluga trenutno nije dostupna.';
    }
    exit;
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $host,
    $port,
    $dbname
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
    PDO::ATTR_TIMEOUT => 5,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $exception) {
    error_log('Database connection failed (' . get_class($exception) . '): ' . $exception->getMessage());
    http_response_code(503);
    if (PHP_SAPI !== 'cli' && str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/backend/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Usluga trenutno nije dostupna.',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Usluga trenutno nije dostupna.';
    }
    exit;
}
