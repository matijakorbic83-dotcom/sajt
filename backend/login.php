<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';

$next = admin_safe_next_target($_GET['next'] ?? 'admin/index.php');
header(
    'Location: ' . admin_app_base_path() . '/admin/login.php?next=' . rawurlencode($next),
    true,
    302
);
exit;
