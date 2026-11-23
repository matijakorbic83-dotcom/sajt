<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/admin_auth.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !admin_verify_csrf_token($_POST['csrf_token'] ?? null)
) {
    http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 405);
    header('Allow: POST');
    exit('Zahtev za odjavu nije ispravan.');
}

admin_logout();
header('Location: ' . admin_app_base_path() . '/admin/login.php?logged_out=1', true, 303);
exit;
