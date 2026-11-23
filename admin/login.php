<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/admin_auth.php';

if (admin_is_logged_in()) {
    header('Location: ' . admin_app_base_path() . '/admin/index.php');
    exit;
}

$showLocalSetup = false;
if (in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)) {
    require_once __DIR__ . '/../backend/db.php';
    $showLocalSetup = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0;
}

$next = admin_safe_next_target($_GET['next'] ?? 'admin/index.php');
$flashMessage = '';
$flashKey = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $next = admin_safe_next_target($_POST['next'] ?? 'admin/index.php');

    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $flashKey = 'admin.invalidRequest';
    } else {
        $remember = ($_POST['remember'] ?? '') === '1';
        $result = admin_login_with_credentials($identifier, $password, $remember);
    }

    if (isset($result) && $result['success']) {
        $cookieParams = session_get_cookie_params();
        if ($remember) {
            setcookie('smilecraft_admin_remember', '1', [
                'expires' => (int) ($_SESSION['admin_remember_until'] ?? time() + (30 * 24 * 60 * 60)),
                'path' => '/',
                'secure' => $cookieParams['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('smilecraft_admin_remember', '', [
                'expires' => time() - 42000,
                'path' => '/',
                'secure' => $cookieParams['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        header('Location: ' . admin_app_base_path() . '/' . $next, true, 303);
        exit;
    }

    if ($flashKey === '') {
        $flashMessage = $result['message'] ?? 'Trenutno nije moguće prijaviti se. Pokušajte ponovo kasnije.';
        $flashKey = $flashMessage === 'Korisničko ime ili lozinka nisu ispravni.'
            ? 'admin.invalidCredentials'
            : 'admin.loginUnavailable';
    }
}
?>
<!doctype html>
<html lang="sr" data-i18n-storage-key="smilecraft-admin-locale" data-theme-storage-key="smilecraft-admin-theme">
<head>
  <script src="../js/theme.js?v=20261114-theme"></script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title data-i18n="admin.loginTitle">Prijava administratora | SmileCraft Dental</title>
  <link rel="stylesheet" href="../css/language-picker.css">
  <script src="../js/i18n.js" defer></script>
  <style>
    :root {
      --bg: #f5f9fc;
      --card: #ffffff;
      --brand: #2d8fcb;
      --brand-dark: #2378ad;
      --ink: #172b3a;
      --muted: #647789;
      --line: #e3ebf1;
      --danger: #9e3a3a;
      --danger-bg: #fff4f4;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, var(--bg) 0%, #ffffff 100%);
      color: var(--ink);
    }
    .login-shell {
      width: min(440px, calc(100% - 2rem));
      padding: 2rem;
      border: 1px solid var(--line);
      border-radius: 1.1rem;
      background: var(--card);
      box-shadow: 0 20px 40px rgba(17, 42, 48, 0.08);
    }
    .login-brand {
      display: inline-flex;
      align-items: center;
      gap: .7rem;
      font-weight: 700;
      color: var(--brand);
      margin-bottom: 1.3rem;
    }
    .brand-mark {
      width: 2.1rem;
      height: 2.1rem;
      display: inline-grid;
      place-items: center;
      border-radius: 50%;
      background: rgba(12, 118, 131, 0.12);
    }
    .brand-mark svg {
      width: 1.2rem;
      height: 1.2rem;
    }
    h1 {
      margin: 0 0 .6rem;
      font-size: 2rem;
    }
    .login-copy {
      margin: 0 0 1.25rem;
      color: var(--muted);
      line-height: 1.6;
    }
    .alert {
      margin-bottom: 1rem;
      padding: .8rem .9rem;
      border-radius: .7rem;
      border: 1px solid transparent;
      font-size: .92rem;
    }
    .alert.error {
      background: var(--danger-bg);
      color: var(--danger);
      border-color: rgba(158, 58, 58, 0.2);
    }
    label {
      display: block;
      margin-bottom: .4rem;
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
    }
    input {
      width: 100%;
      min-height: 3rem;
      margin-bottom: 1rem;
      padding: .8rem .9rem;
      border: 1px solid var(--line);
      border-radius: .75rem;
      font: inherit;
      color: var(--ink);
    }
    input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(12, 118, 131, 0.12);
    }
    .password-row { display:flex;align-items:stretch;gap:.45rem; }
    .password-row input { flex:1;min-width:0; }
    .password-toggle { width:auto;min-width:5.2rem;min-height:3rem;margin:0;padding:0 .7rem;border:1px solid var(--line);background:#fff;color:var(--brand);font-size:.82rem; }
    .password-toggle:hover { background:#f2f8f8; }
    .remember-row { display:flex;align-items:center;gap:.55rem;margin:.1rem 0 1rem;color:var(--muted);font-size:.9rem; }
    .remember-row input { width:1rem;min-height:1rem;height:1rem;margin:0;accent-color:var(--brand); }
    .remember-row label { margin:0;color:var(--muted);font-size:.86rem;font-weight:600;letter-spacing:0;text-transform:none; }
    button {
      width: 100%;
      min-height: 3rem;
      border: none;
      border-radius: .75rem;
      background: var(--brand);
      color: #081e2d;
      font-size: .96rem;
      font-weight: 700;
      cursor: pointer;
    }
    button:hover {
      background: var(--brand-dark);
    }
    .helper {
      margin-top: 1rem;
      font-size: .82rem;
      color: var(--muted);
      text-align: center;
    }
    .language-control {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: .45rem;
      margin-bottom: 1.25rem;
      color: var(--muted);
      font-size: .8rem;
    }
    .language-control select {
      min-height: 2.4rem;
      padding: .4rem .6rem;
      border: 1px solid var(--line);
      border-radius: .6rem;
      background: var(--card);
      color: var(--ink);
      font: inherit;
    }
  </style>
  <link rel="stylesheet" href="../css/theme.css?v=20261114-theme">
</head>
<body class="admin-login">
  <main class="login-shell">
    <div class="language-control">
      <span data-i18n="language.label">Jezik</span>
      <select data-language-select aria-label="Izaberite jezik" data-i18n-attr="aria-label:language.select">
        <option value="sr-Latn" lang="sr-Latn">Srpski — Latinica</option>
        <option value="sr-Cyrl" lang="sr-Cyrl">Српски — Ћирилица</option>
        <option value="en" lang="en">English</option>
        <option value="ru" lang="ru">Русский</option>
        <option value="de" lang="de">Deutsch</option>
      </select>
      <button class="theme-toggle" type="button" data-theme-toggle></button>
    </div>
    <div class="login-brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3.8c2.2 0 3.1 1 5 1s2.8-1 5-1c2.6 0 4.2 2.3 3.6 5.2-.6 2.7-1.8 4.3-2.5 7-.6 2.4-1.4 4.5-2.9 4.5-1.7 0-1.2-4.5-3.2-4.5s-1.5 4.5-3.2 4.5c-1.5 0-2.3-2.1-2.9-4.5-.7-2.7-1.9-4.3-2.5-7C3 6.1 4.4 3.8 7 3.8Z"/></svg>
      </span>
      SmileCraft Dental
    </div>
    <h1 data-i18n="admin.portalTitle">Portal za administratore</h1>
    <p class="login-copy" data-i18n="admin.loginDescription">Prijavite se da biste upravljali zakazivanjima, potvrdama i otkazivanjima termina.</p>
    <?php if ($flashKey !== ''): ?>
      <div class="alert error" role="alert" data-i18n="<?= htmlspecialchars($flashKey, ENT_QUOTES, 'UTF-8') ?>"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
      <div>
        <label for="username" data-i18n="admin.usernameOrEmail">Korisničko ime ili imejl adresa</label>
        <input id="username" name="username" type="text" autocomplete="username" required placeholder="administrator@primer.rs" data-i18n-attr="placeholder:admin.usernamePlaceholder">
      </div>
      <div>
        <label for="password" data-i18n="admin.password">Lozinka</label>
        <div class="password-row">
          <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Unesite lozinku" data-i18n-attr="placeholder:admin.passwordPlaceholder">
          <button class="password-toggle" id="password-toggle" type="button" aria-pressed="false" data-i18n="admin.showPassword">Prikaži</button>
        </div>
      </div>
      <div class="remember-row">
        <input id="remember" name="remember" type="checkbox" value="1">
        <label for="remember" data-i18n="admin.rememberMe">Zapamti me na ovom uređaju</label>
      </div>
      <button type="submit" data-i18n="admin.signIn">Prijavite se</button>
    </form>
    <p class="helper" data-i18n="admin.accountHelper">Koristite administratorski nalog ordinacije koji je kreiran tokom podešavanja.</p>
    <?php if ($showLocalSetup): ?>
      <p class="helper"><a href="setup.php" data-i18n="admin.firstSetupLink">Create the first administrator on this computer</a></p>
    <?php endif; ?>
  </main>
  <script>
    const languageSelect = document.querySelector('[data-language-select]');
    document.addEventListener('DOMContentLoaded', () => {
      const i18n = window.smilecraftI18n;
      languageSelect?.addEventListener('change', () => i18n?.setLocale(languageSelect.value));
      const password = document.getElementById('password');
      const passwordToggle = document.getElementById('password-toggle');
      passwordToggle?.addEventListener('click', () => {
        const reveal = password?.type === 'password';
        if (password) password.type = reveal ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(reveal));
        passwordToggle.textContent = i18n?.t(reveal ? 'admin.hidePassword' : 'admin.showPassword') || '';
      });
      Promise.resolve(i18n?.ready).then(() => {
        if (languageSelect && i18n) languageSelect.value = i18n.getLocale();
      });
      window.addEventListener('smilecraft:localechange', () => {
        if (passwordToggle && i18n) {
          passwordToggle.textContent = i18n.t(password?.type === 'text' ? 'admin.hidePassword' : 'admin.showPassword');
        }
      });
    });
  </script>
</body>
</html>
