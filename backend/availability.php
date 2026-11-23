<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schedule.php';
const MAX_AVAILABILITY_RANGE_DAYS = 31;

function json_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function parse_date(string $date): ?DateTimeImmutable
{
    return clinic_parse_date($date);
}

function get_available_slots(
    PDOStatement $conflictStmt,
    int $clinicId,
    int $serviceDurationMinutes,
    DateTimeImmutable $date,
    array $openingHours
): array {
    if (!clinic_is_working_day($date, $openingHours)) {
        return [];
    }

    $dayStart = clinic_opening_datetime($date, $openingHours);
    $dayEnd = clinic_closing_datetime($date, $openingHours);
    $candidateStart = $dayStart;
    $slotInterval = new DateInterval('PT' . BOOKING_INTERVAL_MINUTES . 'M');
    $slots = [];
    $now = new DateTimeImmutable('now', clinic_timezone());

    while ($candidateStart < $dayEnd) {
        $candidateEnd = $candidateStart->modify('+' . $serviceDurationMinutes . ' minutes');
        if ($candidateStart > $now && clinic_slot_is_allowed($candidateStart, $serviceDurationMinutes, $openingHours)) {
            $conflictStmt->execute([
                ':clinic_id' => $clinicId,
                ':slot_start' => $candidateStart->format('Y-m-d H:i:s'),
                ':slot_end' => $candidateEnd->format('Y-m-d H:i:s'),
            ]);

            if ($conflictStmt->fetchColumn() === false) {
                $slots[] = $candidateStart->format('H:i');
            }
        }

        $candidateStart = $candidateStart->add($slotInterval);
    }

    return $slots;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_error(405, 'Provera dostupnosti podržava samo GET zahteve.');
}

$clinicValue = $_GET['clinic_id'] ?? '';
$serviceValue = $_GET['service_id'] ?? '';
$hasSingleDate = array_key_exists('date', $_GET);
$hasRangeStart = array_key_exists('start_date', $_GET);
$hasRangeEnd = array_key_exists('end_date', $_GET);

if (
    (!is_string($clinicValue) && !is_int($clinicValue)) ||
    (!is_string($serviceValue) && !is_int($serviceValue))
) {
    json_error(422, 'Potrebni su ispravni ID-jevi ordinacije i usluge.');
}

$clinicId = filter_var($clinicValue, FILTER_VALIDATE_INT);
$serviceId = filter_var($serviceValue, FILTER_VALIDATE_INT);

if ($clinicId === false || $clinicId === null || $clinicId <= 0) {
    json_error(422, 'Izaberite ispravnu ordinaciju.');
}

if ($serviceId === false || $serviceId === null || $serviceId <= 0) {
    json_error(422, 'Potrebno je izabrati ispravnu uslugu.');
}

if ($hasSingleDate && ($hasRangeStart || $hasRangeEnd)) {
    json_error(422, 'Navedite datum ili oba datuma: početni i završni.');
}

if (!$hasSingleDate && !$hasRangeStart && !$hasRangeEnd) {
    json_error(422, 'Navedite datum ili oba datuma: početni i završni.');
}

if ($hasRangeStart !== $hasRangeEnd) {
    json_error(422, 'Za opseg datuma obavezni su početni i završni datum.');
}

$singleDate = null;
$rangeStart = null;
$rangeEnd = null;

if ($hasSingleDate) {
    if (!is_string($_GET['date'])) {
        json_error(422, 'Datum mora biti naveden u formatu GGGG-MM-DD.');
    }
    $singleDate = parse_date(trim($_GET['date']));
    if ($singleDate === null) {
        json_error(422, 'Navedite ispravan datum u formatu GGGG-MM-DD.');
    }
} else {
    if (!is_string($_GET['start_date']) || !is_string($_GET['end_date'])) {
        json_error(422, 'Datumi moraju biti navedeni u formatu GGGG-MM-DD.');
    }

    $rangeStart = parse_date(trim($_GET['start_date']));
    $rangeEnd = parse_date(trim($_GET['end_date']));
    if ($rangeStart === null || $rangeEnd === null) {
        json_error(422, 'Navedite ispravne datume u formatu GGGG-MM-DD.');
    }

    if ($rangeEnd < $rangeStart) {
        json_error(422, 'Završni datum mora biti isti ili kasniji od početnog datuma.');
    }

    $rangeLength = (int) $rangeStart->diff($rangeEnd)->format('%a') + 1;
    if ($rangeLength > MAX_AVAILABILITY_RANGE_DAYS) {
        json_error(422, 'Izabrani opseg ne može biti duži od 31 dana.');
    }
}

try {
    $clinicCheck = $pdo->prepare('SELECT id FROM clinics WHERE id = :id LIMIT 1');
    $clinicCheck->execute([':id' => $clinicId]);
    if ($clinicCheck->fetchColumn() === false) {
        json_error(404, 'Izabrana ordinacija nije pronađena.');
    }

    $serviceStmt = $pdo->prepare(
        'SELECT duration_minutes
         FROM services
         WHERE id = :service_id AND clinic_id = :clinic_id AND active = 1
         LIMIT 1'
    );
    $serviceStmt->execute([
        ':service_id' => $serviceId,
        ':clinic_id' => $clinicId,
    ]);
    $serviceRow = $serviceStmt->fetch();
    if ($serviceRow === false) {
        json_error(404, 'Izabrana usluga nije dostupna u ovoj ordinaciji.');
    }

    $serviceDurationMinutes = (int) $serviceRow['duration_minutes'];
    if ($serviceDurationMinutes <= 0) {
        json_error(500, 'Trajanje izabrane usluge nije ispravno.');
    }

    $hoursStmt = $pdo->prepare(
        'SELECT weekday, is_closed, opens_at, closes_at, break_starts_at, break_ends_at
         FROM clinic_opening_hours WHERE clinic_id = :clinic_id ORDER BY weekday'
    );
    $hoursStmt->execute([':clinic_id' => $clinicId]);
    $openingHours = $hoursStmt->fetchAll();

    $conflictStmt = $pdo->prepare(
        'SELECT a.id
         FROM appointments a
         LEFT JOIN services s ON s.id = a.service_id
         WHERE a.clinic_id = :clinic_id
           AND a.status IN (\'pending\', \'confirmed\')
           AND a.appointment_start < :slot_end
           AND DATE_ADD(
                a.appointment_start,
                INTERVAL COALESCE(a.service_duration_minutes, s.duration_minutes, 30) MINUTE
           ) > :slot_start
         LIMIT 1'
    );

    if ($singleDate !== null) {
        $slots = get_available_slots(
            $conflictStmt,
            $clinicId,
            $serviceDurationMinutes,
            $singleDate,
            $openingHours
        );

        echo json_encode([
            'success' => true,
            'date' => $singleDate->format('Y-m-d'),
            'clinic_id' => $clinicId,
            'slots' => $slots,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $dates = [];
    for ($date = $rangeStart; $date <= $rangeEnd; $date = $date->modify('+1 day')) {
        $slots = get_available_slots(
            $conflictStmt,
            $clinicId,
            $serviceDurationMinutes,
            $date,
            $openingHours
        );
        $dates[] = [
            'date' => $date->format('Y-m-d'),
            'available' => count($slots) > 0,
        ];
    }

    echo json_encode([
        'success' => true,
        'clinic_id' => $clinicId,
        'service_id' => $serviceId,
        'start_date' => $rangeStart->format('Y-m-d'),
        'end_date' => $rangeEnd->format('Y-m-d'),
        'dates' => $dates,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $exception) {
    error_log('Availability query failed: ' . $exception->getMessage());
    json_error(500, 'Trenutno nije moguće proveriti dostupnost.');
}
