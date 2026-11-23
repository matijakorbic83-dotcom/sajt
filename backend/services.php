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
    $stmt = $pdo->query(
        'SELECT id, clinic_id, category, name, description, duration_minutes
         FROM services
         WHERE active = 1
         ORDER BY clinic_id, category, name'
    );

    $services = $stmt->fetchAll();

    echo json_encode(
        $services,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

} catch (PDOException $e) {
    error_log('Usluge query failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(
        $services,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

} catch (PDOException $exception) {
    error_log('Services query failed: ' . $exception->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Nije moguće učitati usluge.'
    ], JSON_UNESCAPED_UNICODE);
}