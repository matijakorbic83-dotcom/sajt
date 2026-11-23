<?php

declare(strict_types=1);

function app_load_env_file(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log('Unable to read the application environment file.');
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $key)) {
            continue;
        }

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && str_ends_with($value, '"')) ||
                ($value[0] === "'" && str_ends_with($value, "'")))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

function app_env(string $key, ?string $default = null): ?string
{
    app_load_env_file();

    $value = getenv($key);
    if ($value === false) {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;
    }

    return is_string($value) ? $value : $default;
}
