<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('smilecraft_admin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) (30 * 24 * 60 * 60));

    $appScheme = strtolower((string) parse_url((string) app_env('APP_URL', ''), PHP_URL_SCHEME));
    $isHttps = (
        !empty($_SERVER['HTTPS']) &&
        strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ) || $appScheme === 'https';
    $rememberRequested = ($_POST['remember'] ?? '') === '1';
    $rememberCookie = ($_COOKIE['smilecraft_admin_remember'] ?? '') === '1';
    session_set_cookie_params([
        'lifetime' => $rememberRequested || $rememberCookie ? 30 * 24 * 60 * 60 : 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_app_base_path(): string
{
    $scriptPath = (string) (parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '');
    $directory = dirname(str_replace('\\', '/', $scriptPath));
    if (in_array(basename($directory), ['admin', 'backend'], true)) {
        $directory = dirname($directory);
    }

    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function admin_safe_next_target($target): string
{
    return is_string($target) && in_array($target, ['admin/index.php', 'backend/admin.php'], true)
        ? $target
        : 'admin/index.php';
}

function admin_current_user(): ?array
{
    $user = $_SESSION['admin_user'] ?? null;
    if (
        !is_array($user) ||
        !isset($user['id'], $user['clinic_id']) ||
        (int) $user['id'] <= 0 ||
        (int) $user['clinic_id'] <= 0
    ) {
        return null;
    }

    $now = time();
    $authenticatedAt = $_SESSION['admin_authenticated_at'] ?? null;
    $remember = ($_SESSION['admin_remember'] ?? false) === true;
    $expiresAt = $remember
        ? ($_SESSION['admin_remember_until'] ?? null)
        : (is_int($authenticatedAt) ? $authenticatedAt + (8 * 60 * 60) : null);
    if (
        !is_int($authenticatedAt) ||
        $authenticatedAt <= 0 ||
        $authenticatedAt > $now ||
        !is_int($expiresAt) ||
        $expiresAt <= $now
    ) {
        unset(
            $_SESSION['admin_user'],
            $_SESSION['admin_remember'],
            $_SESSION['admin_remember_until'],
            $_SESSION['admin_authenticated_at'],
            $_SESSION['admin_credential_fingerprint']
        );
        return null;
    }

    global $pdo;
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare(
        'SELECT id, clinic_id, username, email, password_hash, is_active
         FROM admin_users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int) $user['id']]);
    $account = $stmt->fetch();
    $credentialFingerprint = $_SESSION['admin_credential_fingerprint'] ?? null;
    if (
        $account === false ||
        (int) $account['is_active'] !== 1 ||
        (int) $account['clinic_id'] !== (int) $user['clinic_id'] ||
        !is_string($credentialFingerprint) ||
        !hash_equals($credentialFingerprint, hash('sha256', (string) $account['password_hash']))
    ) {
        unset(
            $_SESSION['admin_user'],
            $_SESSION['admin_remember'],
            $_SESSION['admin_remember_until'],
            $_SESSION['admin_authenticated_at'],
            $_SESSION['admin_credential_fingerprint']
        );
        return null;
    }

    return [
        'id' => (int) $account['id'],
        'clinic_id' => (int) $account['clinic_id'],
        'username' => (string) $account['username'],
        'email' => (string) $account['email'],
    ];
}

function admin_is_logged_in(): bool
{
    return admin_current_user() !== null;
}

function admin_require_login(): void
{
    if (admin_is_logged_in()) {
        return;
    }

    $scriptPath = (string) (parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '');
    $next = preg_match('~(?:^|/)backend/admin\.php\z~', $scriptPath) === 1
        ? 'backend/admin.php'
        : 'admin/index.php';
    $loginPath = admin_app_base_path() . '/admin/login.php?next=' . rawurlencode($next);
    header('Location: ' . $loginPath, true, 302);
    exit;
}

function admin_require_json_login(): void
{
    if (!admin_is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Potrebna je prijava.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf_token($token): bool
{
    return is_string($token) &&
        isset($_SESSION['admin_csrf_token']) &&
        is_string($_SESSION['admin_csrf_token']) &&
        hash_equals($_SESSION['admin_csrf_token'], $token);
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    setcookie('smilecraft_admin_remember', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => (bool) session_get_cookie_params()['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_destroy();
}

function admin_login_with_credentials(string $identifier, string $password, bool $remember = false): array
{
    $cleanIdentifier = trim($identifier);
    if ($cleanIdentifier === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'Korisničko ime ili lozinka nisu ispravni.',
        ];
    }

    global $pdo;
    require_once __DIR__ . '/db.php';

    try {
        $stmt = $pdo->prepare(
            'SELECT id, clinic_id, username, email, password_hash
             FROM admin_users
             WHERE is_active = 1
               AND clinic_id IS NOT NULL
               AND (username = :username OR email = :email)
             LIMIT 1'
        );
        $stmt->execute([
            ':username' => $cleanIdentifier,
            ':email' => $cleanIdentifier,
        ]);
        $admin = $stmt->fetch();
    } catch (PDOException $exception) {
        error_log('Administrator authentication lookup failed: ' . $exception->getMessage());
        return [
            'success' => false,
            'message' => 'Trenutno nije moguće prijaviti se. Pokušajte ponovo kasnije.',
        ];
    }

    if ($admin === false || !password_verify($password, (string) $admin['password_hash'])) {
        return [
            'success' => false,
            'message' => 'Korisničko ime ili lozinka nisu ispravni.',
        ];
    }

    session_regenerate_id(true);
    unset($_SESSION['admin_csrf_token']);
    $_SESSION['admin_user'] = [
        'id' => (int) $admin['id'],
        'clinic_id' => (int) $admin['clinic_id'],
        'username' => (string) $admin['username'],
        'email' => (string) $admin['email'],
    ];
    $_SESSION['admin_authenticated_at'] = time();
    $_SESSION['admin_credential_fingerprint'] = hash('sha256', (string) $admin['password_hash']);
    if ($remember) {
        $_SESSION['admin_remember'] = true;
        $_SESSION['admin_remember_until'] = time() + (30 * 24 * 60 * 60);
    } else {
        unset($_SESSION['admin_remember'], $_SESSION['admin_remember_until']);
    }

    try {
        $updateStmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
        $updateStmt->execute([':id' => $admin['id']]);
    } catch (PDOException $exception) {
        error_log('Administrator last-login timestamp update failed: ' . $exception->getMessage());
    }

    return [
        'success' => true,
        'message' => 'Uspešno ste se prijavili.',
        'user' => $_SESSION['admin_user'],
    ];
}
