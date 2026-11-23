<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/admin_auth.php';
admin_require_json_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schedule.php';

function admin_api_reply(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function admin_api_error(int $status, string $code): void
{
    admin_api_reply(['success' => false, 'message' => $code], $status);
}

function admin_api_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? null : $length;
}

function admin_api_text($value, int $maxLength, bool $required = false): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    $length = admin_api_length($value);
    if ($length === null || $length > $maxLength || ($required && $value === '') ||
        preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
        return null;
    }
    return $value;
}

function admin_api_id($value): int|false
{
    if (!is_string($value) && !is_int($value)) {
        return false;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : false;
}

function admin_api_require_tables(PDO $pdo, array $tables): void
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
    );
    foreach ($tables as $table) {
        $stmt->execute([':table' => $table]);
        if ($stmt->fetchColumn() === false) {
            admin_api_error(503, 'DATABASE_MIGRATION_REQUIRED');
        }
    }
}

function admin_api_read_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 16384) {
        admin_api_error(413, 'REQUEST_TOO_LARGE');
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
        admin_api_error(400, 'INVALID_JSON');
    }
    return $payload;
}

function admin_api_hours(PDO $pdo, int $clinicId): array
{
    $stmt = $pdo->prepare(
        'SELECT weekday, is_closed, opens_at, closes_at, break_starts_at, break_ends_at
         FROM clinic_opening_hours WHERE clinic_id = :clinic_id ORDER BY weekday'
    );
    $stmt->execute([':clinic_id' => $clinicId]);
    $rows = $stmt->fetchAll();
    if (count($rows) !== 7) {
        $default = [];
        for ($day = 1; $day <= 7; $day++) {
            $open = $day <= 5;
            $default[] = [
                'weekday' => $day,
                'is_closed' => !$open,
                'opens_at' => $open ? '09:00:00' : null,
                'closes_at' => $open ? '17:00:00' : null,
                'break_starts_at' => $open ? '13:00:00' : null,
                'break_ends_at' => $open ? '14:00:00' : null,
            ];
        }
        return $default;
    }
    return $rows;
}

function admin_api_get_clinic(PDO $pdo, int $clinicId): array
{
    $stmt = $pdo->prepare('SELECT id, name, email, phone, address FROM clinics WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $clinicId]);
    $clinic = $stmt->fetch();
    if ($clinic === false) {
        admin_api_error(404, 'CLINIC_NOT_FOUND');
    }
    $clinic['opening_hours'] = admin_api_hours($pdo, $clinicId);
    return $clinic;
}

$adminUser = admin_current_user();
$clinicId = (int) $adminUser['clinic_id'];
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

admin_api_require_tables($pdo, [
    'admin_users',
    'clinics',
    'appointments',
    'services',
    'clinic_opening_hours',
    'clinic_content_items',
]);

if ($method === 'GET') {
    $section = is_string($_GET['section'] ?? null) ? $_GET['section'] : 'appointments';
    try {
        if ($section === 'dashboard') {
            $today = (new DateTimeImmutable('now', clinic_timezone()))->format('Y-m-d');
            $stmt = $pdo->prepare(
                'SELECT
                    COUNT(*) AS appointments,
                    SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = \'confirmed\' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN status IN (\'pending\', \'confirmed\') THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN DATE(appointment_start) = :today AND status <> \'cancelled\' THEN 1 ELSE 0 END) AS today,
                    COUNT(DISTINCT CASE
                        WHEN TRIM(patient_email) <> \'\' THEN LOWER(TRIM(patient_email))
                        ELSE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(patient_phone, \' \', \'\'), \'-\', \'\'), \'+\', \'\'), \'(\', \'\'), \')\', \'\'), \'.\', \'\'))
                    END) AS patients
                 FROM appointments WHERE clinic_id = :clinic_id'
            );
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':today' => $today,
            ]);
            $totals = $stmt->fetch() ?: [];
            foreach ($totals as $key => $value) {
                $totals[$key] = (int) ($value ?? 0);
            }
            $todayStmt = $pdo->prepare(
                'SELECT a.id, a.patient_name, a.patient_email, a.patient_phone, a.appointment_start,
                        a.status, a.service_duration_minutes AS duration_minutes, s.name AS service_name
                 FROM appointments a LEFT JOIN services s ON s.id = a.service_id
                 WHERE a.clinic_id = :clinic_id AND DATE(a.appointment_start) = :today
                   AND a.status IN (\'pending\', \'confirmed\')
                 ORDER BY a.appointment_start, a.id LIMIT 8'
            );
            $todayStmt->execute([':clinic_id' => $clinicId, ':today' => $today]);
            admin_api_reply([
                'success' => true,
                'totals' => $totals,
                'today_appointments' => $todayStmt->fetchAll(),
                'clinic' => admin_api_get_clinic($pdo, $clinicId),
            ]);
        }

        if ($section === 'clinic') {
            admin_api_reply(['success' => true, 'clinic' => admin_api_get_clinic($pdo, $clinicId)]);
        }

        if ($section === 'services') {
            $stmt = $pdo->prepare(
                'SELECT id, category, name, description, duration_minutes, active
                 FROM services WHERE clinic_id = :clinic_id ORDER BY category, active DESC, name, id'
            );
            $stmt->execute([':clinic_id' => $clinicId]);
            admin_api_reply(['success' => true, 'services' => $stmt->fetchAll()]);
        }

        if ($section === 'content') {
            $type = $_GET['type'] ?? 'all';
            $allowed = ['all', 'dentist', 'review', 'gallery', 'faq'];
            if (!is_string($type) || !in_array($type, $allowed, true)) {
                admin_api_error(422, 'INVALID_CONTENT_TYPE');
            }
            $query = 'SELECT id, content_type, title, body, media_url, sort_order, is_published
                      FROM clinic_content_items WHERE clinic_id = :clinic_id';
            $params = [':clinic_id' => $clinicId];
            if ($type !== 'all') {
                $query .= ' AND content_type = :content_type';
                $params[':content_type'] = $type;
            }
            $query .= ' ORDER BY content_type, sort_order, id';
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            admin_api_reply(['success' => true, 'items' => $stmt->fetchAll()]);
        }

        if ($section !== 'appointments') {
            admin_api_error(404, 'SECTION_NOT_FOUND');
        }

        $status = $_GET['status'] ?? 'active';
        $date = $_GET['date'] ?? '';
        $search = admin_api_text($_GET['search'] ?? '', 120);
        if (!is_string($status) || !in_array($status, ['all', 'active', 'pending', 'confirmed', 'cancelled'], true)) {
            admin_api_error(422, 'INVALID_STATUS_FILTER');
        }
        if (!is_string($date) || ($date !== '' && clinic_parse_date($date) === null)) {
            admin_api_error(422, 'INVALID_DATE_FILTER');
        }
        if ($search === null) {
            admin_api_error(422, 'INVALID_SEARCH');
        }

        $query = 'SELECT a.id, a.patient_name, a.patient_email, a.patient_phone, a.appointment_start,
                         a.status, a.created_at, a.service_id, a.service_duration_minutes AS duration_minutes,
                         s.name AS service_name
                  FROM appointments a LEFT JOIN services s ON s.id = a.service_id
                  WHERE a.clinic_id = :clinic_id';
        $params = [':clinic_id' => $clinicId];
        if ($status === 'active') {
            $query .= " AND a.status IN ('pending', 'confirmed')";
        } elseif ($status !== 'all') {
            $query .= ' AND a.status = :status';
            $params[':status'] = $status;
        }
        if ($date !== '') {
            $query .= ' AND DATE(a.appointment_start) = :appointment_date';
            $params[':appointment_date'] = $date;
        }
        if ($search !== '') {
            $query .= ' AND (a.patient_name LIKE :search_name OR a.patient_email LIKE :search_email
                        OR a.patient_phone LIKE :search_phone OR s.name LIKE :search_service OR CAST(a.id AS CHAR) = :search_id)';
            $needle = '%' . $search . '%';
            $params[':search_name'] = $needle;
            $params[':search_email'] = $needle;
            $params[':search_phone'] = $needle;
            $params[':search_service'] = $needle;
            $params[':search_id'] = $search;
        }
        $query .= ' ORDER BY a.appointment_start DESC, a.id DESC LIMIT 500';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        admin_api_reply(['success' => true, 'appointments' => $stmt->fetchAll()]);
    } catch (PDOException $exception) {
        error_log('Administrator read request failed: ' . $exception->getMessage());
        admin_api_error(500, 'DATABASE_ERROR');
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    admin_api_error(405, 'METHOD_NOT_ALLOWED');
}

$payload = admin_api_read_body();
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!admin_verify_csrf_token($csrfToken)) {
    admin_api_error(403, 'CSRF_INVALID');
}

$action = is_string($payload['action'] ?? null) ? $payload['action'] : '';
try {
    if ($action === 'update_status') {
        $id = admin_api_id($payload['id'] ?? null);
        $status = $payload['status'] ?? null;
        if ($id === false || !is_string($status) || !in_array($status, ['confirmed', 'cancelled'], true)) {
            admin_api_error(422, 'INVALID_APPOINTMENT_UPDATE');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = :id AND clinic_id = :clinic_id FOR UPDATE');
        $stmt->execute([':id' => $id, ':clinic_id' => $clinicId]);
        $current = $stmt->fetchColumn();
        $allowed = $status === 'confirmed' ? $current === 'pending' : in_array($current, ['pending', 'confirmed'], true);
        if (!$allowed) {
            $pdo->rollBack();
            admin_api_error(409, 'STATUS_TRANSITION_NOT_ALLOWED');
        }
        $update = $pdo->prepare('UPDATE appointments SET status = :status WHERE id = :id AND clinic_id = :clinic_id AND status = :current');
        $update->execute([':status' => $status, ':id' => $id, ':clinic_id' => $clinicId, ':current' => $current]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            admin_api_error(409, 'APPOINTMENT_CHANGED');
        }
        $pdo->commit();
        admin_api_reply(['success' => true, 'status' => $status]);
    }

    if ($action === 'update_appointment') {
        $id = admin_api_id($payload['id'] ?? null);
        $name = admin_api_text($payload['patient_name'] ?? null, 190, true);
        $email = admin_api_text($payload['patient_email'] ?? null, 190, true);
        $phone = admin_api_text($payload['patient_phone'] ?? null, 40, true);
        $startInput = $payload['appointment_start'] ?? null;
        if ($id === false || $name === null || $email === null || $phone === null ||
            filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
            !preg_match('/\A[0-9+().\-\s]{6,40}\z/u', $phone) ||
            !is_string($startInput) || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $startInput)) {
            admin_api_error(422, 'INVALID_APPOINTMENT_DETAILS');
        }
        [$date, $time] = explode('T', $startInput, 2);
        $start = clinic_parse_appointment_start($date, $time);
        if ($start === null || $start <= new DateTimeImmutable('now', clinic_timezone())) {
            admin_api_error(422, 'INVALID_APPOINTMENT_DATETIME');
        }

        $pdo->beginTransaction();
        $clinicLock = $pdo->prepare('SELECT id FROM clinics WHERE id = :clinic_id FOR UPDATE');
        $clinicLock->execute([':clinic_id' => $clinicId]);
        $appointmentStmt = $pdo->prepare(
            'SELECT status, service_duration_minutes, appointment_start
             FROM appointments WHERE id = :id AND clinic_id = :clinic_id FOR UPDATE'
        );
        $appointmentStmt->execute([':id' => $id, ':clinic_id' => $clinicId]);
        $appointment = $appointmentStmt->fetch();
        if ($appointment === false || !in_array($appointment['status'], ['pending', 'confirmed'], true)) {
            $pdo->rollBack();
            admin_api_error(404, 'APPOINTMENT_NOT_EDITABLE');
        }
        $schedule = admin_api_hours($pdo, $clinicId);
        if (!clinic_slot_is_allowed($start, (int) $appointment['service_duration_minutes'], $schedule)) {
            $pdo->rollBack();
            admin_api_error(422, 'APPOINTMENT_OUTSIDE_OPENING_HOURS');
        }
        $duration = (int) $appointment['service_duration_minutes'];
        $conflict = $pdo->prepare(
            'SELECT id FROM appointments
             WHERE clinic_id = :clinic_id AND id <> :id AND status IN (\'pending\', \'confirmed\')
               AND appointment_start < :new_end
               AND DATE_ADD(appointment_start, INTERVAL service_duration_minutes MINUTE) > :new_start
             LIMIT 1'
        );
        $conflict->execute([
            ':clinic_id' => $clinicId,
            ':id' => $id,
            ':new_end' => $start->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s'),
            ':new_start' => $start->format('Y-m-d H:i:s'),
        ]);
        if ($conflict->fetchColumn() !== false) {
            $pdo->rollBack();
            admin_api_error(409, 'APPOINTMENT_TIME_UNAVAILABLE');
        }
        $update = $pdo->prepare(
            'UPDATE appointments SET patient_name = :name, patient_email = :email, patient_phone = :phone,
             appointment_start = :appointment_start
             WHERE id = :id AND clinic_id = :clinic_id AND status IN (\'pending\', \'confirmed\')'
        );
        $update->execute([
            ':name' => $name, ':email' => $email, ':phone' => $phone,
            ':appointment_start' => $start->format('Y-m-d H:i:s'), ':id' => $id, ':clinic_id' => $clinicId,
        ]);
        $pdo->commit();
        admin_api_reply(['success' => true]);
    }

    if ($action === 'save_service') {
        $id = ($payload['id'] ?? null) === null || ($payload['id'] ?? '') === '' ? null : admin_api_id($payload['id']);
        $category = admin_api_text($payload['category'] ?? null, 100, true);
        $name = admin_api_text($payload['name'] ?? null, 190, true);
        $description = admin_api_text($payload['description'] ?? '', 2000);
        $duration = filter_var($payload['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
        $active = filter_var($payload['active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (($id === false) || $category === null || $name === null || $description === null || $duration === false ||
            $duration < 15 || $duration > 240 || $duration % 15 !== 0 || $active === null) {
            admin_api_error(422, 'INVALID_SERVICE');
        }
        if ($id === null) {
            $stmt = $pdo->prepare('INSERT INTO services (clinic_id, category, name, description, duration_minutes, active)
                                   VALUES (:clinic_id, :category, :name, :description, :duration, :active)');
            $stmt->execute([':clinic_id' => $clinicId, ':category' => $category, ':name' => $name, ':description' => $description ?: null, ':duration' => $duration, ':active' => (int) $active]);
            admin_api_reply(['success' => true, 'id' => (int) $pdo->lastInsertId()], 201);
        }
        $stmt = $pdo->prepare('UPDATE services SET category = :category, name = :name, description = :description,
                               duration_minutes = :duration, active = :active
                               WHERE id = :id AND clinic_id = :clinic_id');
        $stmt->execute([':category' => $category, ':name' => $name, ':description' => $description ?: null, ':duration' => $duration, ':active' => (int) $active, ':id' => $id, ':clinic_id' => $clinicId]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM services WHERE id = :id AND clinic_id = :clinic_id');
            $exists->execute([':id' => $id, ':clinic_id' => $clinicId]);
            if ($exists->fetchColumn() === false) {
                admin_api_error(404, 'SERVICE_NOT_FOUND');
            }
        }
        admin_api_reply(['success' => true]);
    }

    if ($action === 'save_content') {
        $id = ($payload['id'] ?? null) === null || ($payload['id'] ?? '') === '' ? null : admin_api_id($payload['id']);
        $type = $payload['content_type'] ?? null;
        $title = admin_api_text($payload['title'] ?? null, 190, true);
        $body = admin_api_text($payload['body'] ?? '', 5000);
        $media = admin_api_text($payload['media_url'] ?? '', 2048);
        $sort = filter_var($payload['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $published = filter_var($payload['is_published'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($id === false || !is_string($type) || !in_array($type, ['dentist', 'review', 'gallery', 'faq'], true) ||
            $title === null || $body === null || $media === null || $sort === false || $sort < 0 || $sort > 65535 ||
            $published === null) {
            admin_api_error(422, 'INVALID_CONTENT');
        }
        if ($media !== '') {
            $absoluteUrl = filter_var($media, FILTER_VALIDATE_URL) !== false;
            $scheme = strtolower((string) parse_url($media, PHP_URL_SCHEME));
            $relativeImagePath = preg_match('~\A/(?!/)~', $media) === 1;
            if (
                preg_match('/[\x00-\x20]/', $media) ||
                ($absoluteUrl && !in_array($scheme, ['http', 'https'], true)) ||
                (!$absoluteUrl && !$relativeImagePath)
            ) {
                admin_api_error(422, 'INVALID_MEDIA_URL');
            }
        }
        if (($type === 'gallery' && $media === '') || ($type !== 'gallery' && $body === '')) {
            admin_api_error(422, 'CONTENT_FIELDS_REQUIRED');
        }
        if ($id === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO clinic_content_items
                 (clinic_id, content_type, title, body, media_url, sort_order, is_published)
                 VALUES (:clinic_id, :type, :title, :body, :media, :sort, :published)'
            );
            $stmt->execute([':clinic_id' => $clinicId, ':type' => $type, ':title' => $title, ':body' => $body ?: null, ':media' => $media ?: null, ':sort' => $sort, ':published' => (int) $published]);
            admin_api_reply(['success' => true, 'id' => (int) $pdo->lastInsertId()], 201);
        }
        $stmt = $pdo->prepare(
            'UPDATE clinic_content_items SET content_type = :type, title = :title, body = :body,
             media_url = :media, sort_order = :sort, is_published = :published
             WHERE id = :id AND clinic_id = :clinic_id'
        );
        $stmt->execute([':type' => $type, ':title' => $title, ':body' => $body ?: null, ':media' => $media ?: null, ':sort' => $sort, ':published' => (int) $published, ':id' => $id, ':clinic_id' => $clinicId]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM clinic_content_items WHERE id = :id AND clinic_id = :clinic_id');
            $exists->execute([':id' => $id, ':clinic_id' => $clinicId]);
            if ($exists->fetchColumn() === false) {
                admin_api_error(404, 'CONTENT_NOT_FOUND');
            }
        }
        admin_api_reply(['success' => true]);
    }

    if ($action === 'delete_content') {
        $id = admin_api_id($payload['id'] ?? null);
        if ($id === false) {
            admin_api_error(422, 'INVALID_CONTENT_ID');
        }
        $stmt = $pdo->prepare('DELETE FROM clinic_content_items WHERE id = :id AND clinic_id = :clinic_id');
        $stmt->execute([':id' => $id, ':clinic_id' => $clinicId]);
        if ($stmt->rowCount() !== 1) {
            admin_api_error(404, 'CONTENT_NOT_FOUND');
        }
        admin_api_reply(['success' => true]);
    }

    if ($action === 'save_clinic') {
        $name = admin_api_text($payload['name'] ?? null, 190, true);
        $email = admin_api_text($payload['email'] ?? '', 190);
        $phone = admin_api_text($payload['phone'] ?? '', 40);
        $address = admin_api_text($payload['address'] ?? '', 255);
        $hours = $payload['opening_hours'] ?? null;
        if ($name === null || $email === null || $phone === null || $address === null ||
            ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) || !is_array($hours) || count($hours) !== 7) {
            admin_api_error(422, 'INVALID_CLINIC_PROFILE');
        }
        $normalized = [];
        foreach ($hours as $hour) {
            if (!is_array($hour)) {
                admin_api_error(422, 'INVALID_OPENING_HOURS');
            }
            $weekday = filter_var($hour['weekday'] ?? null, FILTER_VALIDATE_INT);
            $closed = filter_var($hour['is_closed'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($weekday === false || $weekday < 1 || $weekday > 7 || isset($normalized[$weekday]) || $closed === null) {
                admin_api_error(422, 'INVALID_OPENING_HOURS');
            }
            $times = [];
            foreach (['opens_at', 'closes_at', 'break_starts_at', 'break_ends_at'] as $field) {
                $value = $hour[$field] ?? '';
                if ($value === '') {
                    $times[$field] = null;
                } elseif (is_string($value) && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value)) {
                    $times[$field] = $value . ':00';
                } else {
                    admin_api_error(422, 'INVALID_OPENING_HOURS');
                }
            }
            if (!$closed) {
                foreach (['opens_at', 'closes_at', 'break_starts_at', 'break_ends_at'] as $timeField) {
                    if ($times[$timeField] !== null && (int) substr($times[$timeField], 3, 2) % 30 !== 0) {
                        admin_api_error(422, 'INVALID_OPENING_HOURS');
                    }
                }
                if ($times['opens_at'] === null || $times['closes_at'] === null ||
                    $times['opens_at'] >= $times['closes_at']) {
                    admin_api_error(422, 'INVALID_OPENING_HOURS');
                }
                $breakProvided = $times['break_starts_at'] !== null || $times['break_ends_at'] !== null;
                if ($breakProvided && (
                    $times['break_starts_at'] === null || $times['break_ends_at'] === null ||
                    $times['break_starts_at'] < $times['opens_at'] ||
                    $times['break_ends_at'] > $times['closes_at'] ||
                    $times['break_starts_at'] >= $times['break_ends_at']
                )) {
                    admin_api_error(422, 'INVALID_OPENING_HOURS');
                }
            } else {
                $times = array_fill_keys(['opens_at', 'closes_at', 'break_starts_at', 'break_ends_at'], null);
            }
            $normalized[$weekday] = ['weekday' => $weekday, 'is_closed' => (int) $closed] + $times;
        }
        if (count($normalized) !== 7) {
            admin_api_error(422, 'INVALID_OPENING_HOURS');
        }

        $pdo->beginTransaction();
        $clinicLock = $pdo->prepare('SELECT id FROM clinics WHERE id = :id FOR UPDATE');
        $clinicLock->execute([':id' => $clinicId]);
        if ($clinicLock->fetchColumn() === false) {
            $pdo->rollBack();
            admin_api_error(404, 'CLINIC_NOT_FOUND');
        }
        $update = $pdo->prepare('UPDATE clinics SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :id');
        $update->execute([':name' => $name, ':email' => $email ?: null, ':phone' => $phone ?: null, ':address' => $address ?: null, ':id' => $clinicId]);
        $hourUpdate = $pdo->prepare(
            'INSERT INTO clinic_opening_hours (clinic_id, weekday, is_closed, opens_at, closes_at, break_starts_at, break_ends_at)
             VALUES (:clinic_id, :weekday, :closed, :opens, :closes, :break_start, :break_end)
             ON DUPLICATE KEY UPDATE is_closed = VALUES(is_closed), opens_at = VALUES(opens_at),
             closes_at = VALUES(closes_at), break_starts_at = VALUES(break_starts_at), break_ends_at = VALUES(break_ends_at)'
        );
        foreach ($normalized as $hour) {
            $hourUpdate->execute([
                ':clinic_id' => $clinicId, ':weekday' => $hour['weekday'], ':closed' => $hour['is_closed'],
                ':opens' => $hour['opens_at'], ':closes' => $hour['closes_at'],
                ':break_start' => $hour['break_starts_at'], ':break_end' => $hour['break_ends_at'],
            ]);
        }
        $pdo->commit();
        admin_api_reply(['success' => true, 'clinic' => admin_api_get_clinic($pdo, $clinicId)]);
    }

    admin_api_error(400, 'UNSUPPORTED_ACTION');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Administrator mutation failed: ' . $exception->getMessage());
    admin_api_error(500, 'DATABASE_ERROR');
}
