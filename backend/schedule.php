<?php

declare(strict_types=1);

const CLINIC_TIMEZONE = 'Europe/Belgrade';
const CLINIC_OPENING_TIME = '09:00';
const CLINIC_CLOSING_TIME = '17:00';
const CLINIC_BREAK_START_TIME = '13:00';
const CLINIC_BREAK_END_TIME = '14:00';
const BOOKING_INTERVAL_MINUTES = 30;

function clinic_timezone(): DateTimeZone
{
    static $timezone;

    return $timezone ??= new DateTimeZone(CLINIC_TIMEZONE);
}

function clinic_parse_date(string $date): ?DateTimeImmutable
{
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, clinic_timezone());
    $errors = DateTimeImmutable::getLastErrors();

    if (
        $parsed === false ||
        ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $parsed->format('Y-m-d') !== $date
    ) {
        return null;
    }

    return $parsed;
}

function clinic_parse_appointment_start(string $date, string $time): ?DateTimeImmutable
{
    if (!preg_match('/\A\d{2}:\d{2}\z/', $time)) {
        return null;
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, clinic_timezone());
    $errors = DateTimeImmutable::getLastErrors();

    if (
        $start === false ||
        ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $start->format('Y-m-d H:i') !== $date . ' ' . $time
    ) {
        return null;
    }

    return $start;
}

function clinic_day_schedule(DateTimeImmutable $date, ?array $openingHours = null): array
{
    $weekday = (int) $date->format('N');
    if ($openingHours !== null) {
        foreach ($openingHours as $hours) {
            if ((int) ($hours['weekday'] ?? 0) === $weekday) {
                return $hours;
            }
        }
    }

    $open = $weekday <= 5;
    return [
        'weekday' => $weekday,
        'is_closed' => !$open,
        'opens_at' => $open ? CLINIC_OPENING_TIME : null,
        'closes_at' => $open ? CLINIC_CLOSING_TIME : null,
        'break_starts_at' => $open ? CLINIC_BREAK_START_TIME : null,
        'break_ends_at' => $open ? CLINIC_BREAK_END_TIME : null,
    ];
}

function clinic_is_working_day(DateTimeImmutable $date, ?array $openingHours = null): bool
{
    $hours = clinic_day_schedule($date, $openingHours);
    return !(bool) ($hours['is_closed'] ?? true) &&
        is_string($hours['opens_at'] ?? null) &&
        is_string($hours['closes_at'] ?? null);
}

function clinic_time_parts(string $time): array
{
    [$hour, $minute] = array_map('intval', explode(':', $time, 2));

    return [$hour, $minute];
}

function clinic_opening_datetime(DateTimeImmutable $date, ?array $openingHours = null): DateTimeImmutable
{
    $hours = clinic_day_schedule($date, $openingHours);
    $time = substr((string) ($hours['opens_at'] ?? CLINIC_OPENING_TIME), 0, 5);
    return $date->setTime(...clinic_time_parts($time));
}

function clinic_closing_datetime(DateTimeImmutable $date, ?array $openingHours = null): DateTimeImmutable
{
    $hours = clinic_day_schedule($date, $openingHours);
    $time = substr((string) ($hours['closes_at'] ?? CLINIC_CLOSING_TIME), 0, 5);
    return $date->setTime(...clinic_time_parts($time));
}

function clinic_slot_is_allowed(
    DateTimeImmutable $start,
    int $durationMinutes,
    ?array $openingHours = null
): bool
{
    if ($durationMinutes <= 0 || !clinic_is_working_day($start, $openingHours)) {
        return false;
    }

    $start = $start->setTimezone(clinic_timezone());
    $hours = clinic_day_schedule($start, $openingHours);
    $dayStart = clinic_opening_datetime($start, $openingHours);
    $dayEnd = clinic_closing_datetime($start, $openingHours);
    $end = $start->modify('+' . $durationMinutes . ' minutes');
    [$openingHour, $openingMinute] = clinic_time_parts(substr((string) $hours['opens_at'], 0, 5));
    $minutesFromOpening = ((int) $start->format('G') * 60) + (int) $start->format('i')
        - ($openingHour * 60 + $openingMinute);

    if (
        $start < $dayStart ||
        $end > $dayEnd ||
        $minutesFromOpening < 0 ||
        $minutesFromOpening % BOOKING_INTERVAL_MINUTES !== 0 ||
        $start->format('s') !== '00'
    ) {
        return false;
    }

    $breakStartValue = $hours['break_starts_at'] ?? null;
    $breakEndValue = $hours['break_ends_at'] ?? null;
    if (!is_string($breakStartValue) || !is_string($breakEndValue)) {
        return true;
    }
    $breakStart = $start->setTime(...clinic_time_parts(substr($breakStartValue, 0, 5)));
    $breakEnd = $start->setTime(...clinic_time_parts(substr($breakEndValue, 0, 5)));

    return !($start < $breakEnd && $end > $breakStart);
}
