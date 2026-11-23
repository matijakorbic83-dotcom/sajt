<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/appointment_mailer.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/schedule.php';

function booking_json_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function booking_text_length(string $value): ?int
{
    $count = preg_match_all('/./us', $value);

    return $count === false ? null : $count;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    booking_json_error(405, 'METHOD_NOT_ALLOWED');
}

$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
if ($contentType !== 'application/json') {
    booking_json_error(415, 'UNSUPPORTED_MEDIA_TYPE');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || strlen($rawBody) > 8192) {
    booking_json_error(413, 'REQUEST_TOO_LARGE');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    booking_json_error(400, 'INVALID_JSON');
}

$locale = appointment_normalize_locale($payload['locale'] ?? null);
$patientName = is_string($payload['patient_name'] ?? null) ? trim($payload['patient_name']) : '';
$patientEmail = is_string($payload['patient_email'] ?? null) ? trim($payload['patient_email']) : '';
$patientPhone = is_string($payload['patient_phone'] ?? null) ? trim($payload['patient_phone']) : '';
$clinicValue = $payload['clinic_id'] ?? null;
$serviceValue = $payload['service_id'] ?? null;
$clinicId = is_string($clinicValue) || is_int($clinicValue)
    ? filter_var($clinicValue, FILTER_VALIDATE_INT)
    : false;
$serviceId = is_string($serviceValue) || is_int($serviceValue)
    ? filter_var($serviceValue, FILTER_VALIDATE_INT)
    : false;

if (
    $patientName === '' ||
    ($nameLength = booking_text_length($patientName)) === null ||
    $nameLength > 190 ||
    preg_match('/[\x00-\x1F\x7F]/u', $patientName)
) {
    booking_json_error(422, 'INVALID_PATIENT_NAME');
}

if (
    $patientEmail === '' ||
    ($emailLength = booking_text_length($patientEmail)) === null ||
    $emailLength > 190 ||
    filter_var($patientEmail, FILTER_VALIDATE_EMAIL) === false
) {
    booking_json_error(422, 'INVALID_PATIENT_EMAIL');
}

if (
    $patientPhone === '' ||
    ($phoneLength = booking_text_length($patientPhone)) === null ||
    $phoneLength > 40 ||
    !preg_match('/\A[0-9+().\-\s]{6,40}\z/u', $patientPhone)
) {
    booking_json_error(422, 'INVALID_PATIENT_PHONE');
}

if ($clinicId === false || $clinicId <= 0) {
    booking_json_error(422, 'INVALID_CLINIC_ID');
}

if ($serviceId === false || $serviceId <= 0) {
    booking_json_error(422, 'INVALID_SERVICE_ID');
}

$appointmentDate = is_string($payload['appointment_date'] ?? null)
    ? trim($payload['appointment_date'])
    : '';
$appointmentTime = is_string($payload['appointment_time'] ?? null)
    ? trim($payload['appointment_time'])
    : '';
$appointmentStartInput = $payload['appointment_start'] ?? null;
if ($appointmentStartInput !== null) {
    if (!is_string($appointmentStartInput) || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $appointmentStartInput)) {
        booking_json_error(422, 'INVALID_APPOINTMENT_START');
    }

    [$appointmentDate, $appointmentTime] = explode('T', $appointmentStartInput, 2);
}

$appointmentDateTime = clinic_parse_appointment_start($appointmentDate, $appointmentTime);
if ($appointmentDateTime === null) {
    booking_json_error(422, 'INVALID_APPOINTMENT_DATETIME');
}

if ($appointmentDateTime <= new DateTimeImmutable('now', clinic_timezone())) {
    booking_json_error(422, 'APPOINTMENT_MUST_BE_FUTURE');
}

try {
    $pdo->beginTransaction();

    $clinicLock = $pdo->prepare('SELECT id FROM clinics WHERE id = :clinic_id LIMIT 1 FOR UPDATE');
    $clinicLock->execute([':clinic_id' => $clinicId]);
    if ($clinicLock->fetchColumn() === false) {
        $pdo->rollBack();
        booking_json_error(404, 'CLINIC_NOT_FOUND');
    }

    $serviceStmt = $pdo->prepare(
        'SELECT
            s.duration_minutes,
            s.name AS service_name,
            c.name AS clinic_name,
            c.email AS clinic_email,
            c.phone AS clinic_phone,
            c.address AS clinic_address
         FROM services s
         INNER JOIN clinics c ON c.id = s.clinic_id
         WHERE s.id = :service_id
           AND s.clinic_id = :clinic_id
           AND s.active = 1
         LIMIT 1 FOR UPDATE'
    );
    $serviceStmt->execute([
        ':service_id' => $serviceId,
        ':clinic_id' => $clinicId,
    ]);
    $service = $serviceStmt->fetch();
    if ($service === false) {
        $pdo->rollBack();
        booking_json_error(404, 'SERVICE_UNAVAILABLE');
    }

    $durationMinutes = (int) $service['duration_minutes'];
    if ($durationMinutes <= 0) {
        $pdo->rollBack();
        error_log('Active service has an invalid appointment duration. Service ID ' . $serviceId . '.');
        booking_json_error(500, 'SERVICE_CONFIGURATION_ERROR');
    }

    $hoursStmt = $pdo->prepare(
        'SELECT weekday, is_closed, opens_at, closes_at, break_starts_at, break_ends_at
         FROM clinic_opening_hours WHERE clinic_id = :clinic_id ORDER BY weekday'
    );
    $hoursStmt->execute([':clinic_id' => $clinicId]);
    $openingHours = $hoursStmt->fetchAll();

    if (!clinic_slot_is_allowed($appointmentDateTime, $durationMinutes, $openingHours)) {
        $pdo->rollBack();
        booking_json_error(422, 'INVALID_APPOINTMENT_SLOT');
    }

    $conflictStmt = $pdo->prepare(
        'SELECT a.id
         FROM appointments a
         LEFT JOIN services s ON s.id = a.service_id
         WHERE a.clinic_id = :clinic_id
           AND a.status IN (\'pending\', \'confirmed\')
           AND a.appointment_start < :new_end
           AND DATE_ADD(
                a.appointment_start,
                INTERVAL COALESCE(a.service_duration_minutes, s.duration_minutes, 30) MINUTE
           ) > :new_start
         LIMIT 1 FOR UPDATE'
    );
    $appointmentEnd = $appointmentDateTime->modify('+' . $durationMinutes . ' minutes');
    $conflictStmt->execute([
        ':clinic_id' => $clinicId,
        ':new_start' => $appointmentDateTime->format('Y-m-d H:i:s'),
        ':new_end' => $appointmentEnd->format('Y-m-d H:i:s'),
    ]);

    if ($conflictStmt->fetchColumn() !== false) {
        $pdo->rollBack();
        booking_json_error(409, 'SLOT_UNAVAILABLE');
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO appointments (
            clinic_id,
            service_id,
            service_duration_minutes,
            patient_name,
            patient_email,
            patient_phone,
            `language`,
            appointment_start,
            status,
            created_at
         ) VALUES (
            :clinic_id,
            :service_id,
            :duration_minutes,
            :patient_name,
            :patient_email,
            :patient_phone,
            :language,
            :appointment_start,
            \'pending\',
            NOW()
         )'
    );
    $insertStmt->execute([
        ':clinic_id' => $clinicId,
        ':service_id' => $serviceId,
        ':duration_minutes' => $durationMinutes,
        ':patient_name' => $patientName,
        ':patient_email' => $patientEmail,
        ':patient_phone' => $patientPhone,
        ':language' => $locale,
        ':appointment_start' => $appointmentDateTime->format('Y-m-d H:i:s'),
    ]);

    $appointmentId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $emailDelivery = send_appointment_notifications([
        'appointment_id' => $appointmentId,
        'patient_name' => $patientName,
        'patient_email' => $patientEmail,
        'patient_phone' => $patientPhone,
        'language' => $locale,
        'appointment_start' => $appointmentDateTime->format('Y-m-d H:i:s'),
        'status' => 'pending',
        'clinic_name' => $service['clinic_name'],
        'clinic_email' => $service['clinic_email'],
        'clinic_phone' => $service['clinic_phone'],
        'clinic_address' => $service['clinic_address'],
        'service_name' => $service['service_name'],
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'BOOKING_RECEIVED',
        'status' => 'pending',
        'locale' => $locale,
        'appointment_id' => $appointmentId,
        'email_delivery' => $emailDelivery,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Appointment booking failed: ' . $exception->getMessage());
    booking_json_error(500, 'BOOKING_SAVE_FAILED');
}
