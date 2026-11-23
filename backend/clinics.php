<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Dozvoljeni su samo GET zahtevi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query('SELECT id, name FROM clinics ORDER BY id ASC');
    $clinics = $stmt->fetchAll();

    $normalized = array_map(static function ($clinic) {
        return [
            'id' => (int) $clinic['id'],
            'name' => (string) $clinic['name'],
        ];
    }, $clinics);

    echo json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $exception) {
    error_log('Clinics query failed: ' . $exception->getMessage());

    http_response_code(500);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Nije moguće učitati ordinacije.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
