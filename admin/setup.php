<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/admin_auth.php';
require_once __DIR__ . '/../backend/db.php';

function is_local_setup_request(): bool
{
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $host = is_string($hostHeader) ? parse_url('http://' . $hostHeader, PHP_URL_HOST) : false;
    return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true) &&
        is_string($host) &&
        in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
}

function has_proxy_forwarding_headers(): bool
{
    foreach (['HTTP_FORWARDED', 'HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_REAL_IP'] as $header) {
        if (isset($_SERVER[$header]) && is_string($_SERVER[$header]) && trim($_SERVER[$header]) !== '') {
            return true;
        }
    }

    return false;
}

function setup_text_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? null : $length;
}

$setupSecret = trim((string) app_env('ADMIN_SETUP_KEY', ''));
$forwardedRequest = has_proxy_forwarding_headers();
if (!is_local_setup_request() || ($forwardedRequest && $setupSecret === '')) {
    http_response_code(404);
    exit('Not found.');
}

$errorKey = '';
$clinics = [];
$setupAvailable = false;
try {
    $setupAvailable = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0;
    if (!$setupAvailable) {
        http_response_code(404);
        exit('Administrator setup is already complete.');
    }
    $clinics = $pdo->query('SELECT id, name FROM clinics ORDER BY name, id')->fetchAll();
} catch (PDOException $exception) {
    error_log('Local administrator setup check failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Setup is temporarily unavailable. Apply the database migrations and try again.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errorKey = 'csrf';
    } elseif ($setupSecret !== '' && (
        !is_string($_POST['setup_key'] ?? null) ||
        !hash_equals($setupSecret, $_POST['setup_key'])
    )) {
        http_response_code(403);
        $errorKey = 'setup_key';
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $clinicValue = is_string($_POST['clinic_id'] ?? null) ? $_POST['clinic_id'] : '';
        $clinicId = filter_var($clinicValue, FILTER_VALIDATE_INT);
        $usernameLength = setup_text_length($username);
        if ($usernameLength === null || $usernameLength < 3 || $usernameLength > 80 ||
            preg_match('/[\x00-\x20\x7F]/u', $username) ||
            strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
            strlen($password) < 12 || strlen($password) > 1024 ||
            $clinicId === false || $clinicId <= 0) {
            $errorKey = 'invalid';
        } else {
            try {
                $pdo->beginTransaction();
                $setupLock = $pdo->query('SELECT id FROM clinics ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
                $clinicCheck = $pdo->prepare('SELECT id FROM clinics WHERE id = :id LIMIT 1');
                $clinicCheck->execute([':id' => $clinicId]);
                if ($setupLock === false || $clinicCheck->fetchColumn() === false) {
                    $pdo->rollBack();
                    $errorKey = 'clinic';
                } elseif ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() !== 0) {
                    $pdo->rollBack();
                    http_response_code(404);
                    exit('Administrator setup is already complete.');
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO admin_users (clinic_id, username, email, password_hash, is_active)
                         VALUES (:clinic_id, :username, :email, :password_hash, 1)'
                    );
                    $insert->execute([
                        ':clinic_id' => $clinicId,
                        ':username' => $username,
                        ':email' => $email,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $pdo->commit();
                    header('Location: login.php?setup=complete', true, 303);
                    exit;
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Local administrator account creation failed: ' . $exception->getMessage());
                $errorKey = 'duplicate';
            }
        }
    }
}

$messages = [
    'csrf' => 'The setup request expired. Reload this page and try again.',
    'setup_key' => 'The setup key is incorrect.',
    'invalid' => 'Check the username, email, clinic, and password. Passwords must be at least 12 characters.',
    'clinic' => 'Select an existing clinic.',
    'duplicate' => 'The account could not be created. The username or email may already be in use.',
];
?>
<!doctype html>
<html lang="en" data-theme-storage-key="smilecraft-admin-theme">
<head>
  <script src="../js/theme.js?v=20261114-theme"></script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Local administrator setup | SmileCraft Dental</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: var(--surface); color: var(--text); font: 16px/1.5 system-ui, sans-serif; }
    main { width: min(100%, 470px); padding: 2rem; border: 1px solid var(--border); border-radius: 1rem; background: var(--card); box-shadow: var(--shadow); }
    h1 { margin: 0 0 .4rem; font-size: 1.8rem; }
    p { color: var(--text-muted); }
    label { display: block; margin: 1rem 0 .35rem; font-weight: 650; }
    input, select { width: 100%; min-height: 2.8rem; padding: .65rem .75rem; border: 1px solid var(--border); border-radius: .55rem; background: var(--input-background); color: var(--text); font: inherit; }
    input:focus-visible, select:focus-visible, button:focus-visible { outline: 3px solid var(--primary); outline-offset: 3px; }
    button { width: 100%; min-height: 2.9rem; margin-top: 1.4rem; border: 0; border-radius: .6rem; background: var(--primary); color: #10232d; font: inherit; font-weight: 700; cursor: pointer; }
    button:hover { background: var(--primary-hover); }
    .alert { padding: .8rem; border-radius: .5rem; background: #fff0f1; color: #922f3c; }
    .notice { padding: .75rem; border-radius: .5rem; background: var(--primary-soft); font-size: .9rem; }
    .setup-theme-control .theme-toggle { width: auto; min-height: 2.7rem; margin: 0; padding: .55rem .8rem; border: 1px solid var(--border); background: var(--card); color: var(--text); }
  </style>
  <link rel="stylesheet" href="../css/theme.css?v=20261114-theme">
</head>
<body class="admin-setup">
  <main>
    <div class="setup-theme-control"><button class="theme-toggle" type="button" data-theme-toggle></button></div>
    <h1>Set up the first administrator</h1>
    <p>Create the clinic administrator account for this installation.</p>
    <p class="notice">This page is available only through a loopback address and closes permanently after the first account is created. Keep your password safe.</p>
    <?php if ($errorKey !== ''): ?><p class="alert" role="alert"><?= htmlspecialchars($messages[$errorKey] ?? 'Setup failed.', ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($setupSecret !== ''): ?>
        <label for="setup_key">One-time setup key</label>
        <input id="setup_key" name="setup_key" type="password" autocomplete="off" required>
      <?php endif; ?>
      <label for="clinic_id">Clinic</label>
      <select id="clinic_id" name="clinic_id" required>
        <?php foreach ($clinics as $clinic): ?>
          <option value="<?= (int) $clinic['id'] ?>" <?= (string) ($_POST['clinic_id'] ?? '') === (string) $clinic['id'] ? 'selected' : '' ?>><?= htmlspecialchars($clinic['name'], ENT_QUOTES, 'UTF-8') ?> (ID <?= (int) $clinic['id'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <label for="username">Username</label>
      <input id="username" name="username" minlength="3" maxlength="80" autocomplete="username" required value="<?= htmlspecialchars(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', ENT_QUOTES, 'UTF-8') ?>">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', ENT_QUOTES, 'UTF-8') ?>">
      <label for="password">Password (minimum 12 characters)</label>
      <input id="password" name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required>
      <button type="submit">Create administrator</button>
    </form>
  </main>
</body>
</html>
