<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/db.php';

$username = trim((string) app_env('ADMIN_USERNAME', ''));
$email = trim((string) app_env('ADMIN_EMAIL', ''));
$password = (string) app_env('ADMIN_PASSWORD', '');
$clinicValue = trim((string) app_env('ADMIN_CLINIC_ID', ''));

if ($username === '') {
    $username = trim((string) readline('Admin username: '));
}
if ($email === '') {
    $email = trim((string) readline('Admin email: '));
}
if ($clinicValue === '') {
    $clinicValue = trim((string) readline('Clinic ID: '));
}
if ($password === '') {
    $password = (string) readline('Admin password (input is visible): ');
}

$clinicId = filter_var($clinicValue, FILTER_VALIDATE_INT);
if ($username === '' || $email === '' || $password === '' || $clinicId === false || $clinicId <= 0) {
    fwrite(STDERR, "Username, email, clinic ID, and password are required.\n");
    exit(1);
}

if (strlen($username) > 80 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
    fwrite(STDERR, "Username or email address is invalid.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters long.\n");
    exit(1);
}

try {
    $clinicStmt = $pdo->prepare('SELECT id FROM clinics WHERE id = :id LIMIT 1');
    $clinicStmt->execute([':id' => $clinicId]);
    if ($clinicStmt->fetchColumn() === false) {
        fwrite(STDERR, "The selected clinic does not exist.\n");
        exit(1);
    }

    $pdo->beginTransaction();
    $existing = $pdo->prepare(
        'SELECT id FROM admin_users WHERE username = :username OR email = :email FOR UPDATE'
    );
    $existing->execute([
        ':username' => $username,
        ':email' => $email,
    ]);
    $matches = $existing->fetchAll();

    if (count($matches) > 1) {
        $pdo->rollBack();
        fwrite(STDERR, "The username and email belong to different administrator accounts.\n");
        exit(1);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Password hashing failed.');
    }

    if (count($matches) === 1) {
        $stmt = $pdo->prepare(
            'UPDATE admin_users
             SET username = :username,
                 email = :email,
                 password_hash = :password_hash,
                 clinic_id = :clinic_id,
                 is_active = 1
             WHERE id = :id'
        );
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':clinic_id' => $clinicId,
            ':id' => (int) $matches[0]['id'],
        ]);
        $pdo->commit();
        fwrite(STDOUT, "Administrator account updated successfully.\n");
        exit(0);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (username, email, password_hash, clinic_id, is_active)
         VALUES (:username, :email, :password_hash, :clinic_id, 1)'
    );
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':clinic_id' => $clinicId,
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Administrator account created successfully.\n");
} catch (PDOException | RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Administrator initialization failed: ' . $exception->getMessage());
    fwrite(STDERR, "Failed to create administrator account. Check the application error log.\n");
    exit(1);
}
