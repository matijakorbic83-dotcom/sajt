<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/i18n.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';

function get_appointment_mail_config(): ?array
{
    $required = [
        'SMTP_HOST' => app_env('SMTP_HOST'),
        'SMTP_USERNAME' => app_env('SMTP_USERNAME'),
        'SMTP_PASSWORD' => app_env('SMTP_PASSWORD'),
        'SMTP_FROM_EMAIL' => app_env('SMTP_FROM_EMAIL'),
    ];
    $missing = [];

    foreach ($required as $name => $value) {
        if (!is_string($value) || trim($value) === '') {
            $missing[] = $name;
        }
    }

    $fromEmail = $required['SMTP_FROM_EMAIL'];
    if (
        is_string($fromEmail) &&
        trim($fromEmail) !== '' &&
        filter_var(trim($fromEmail), FILTER_VALIDATE_EMAIL) === false
    ) {
        $missing[] = 'SMTP_FROM_EMAIL (valid email address required)';
    }

    $portValue = app_env('SMTP_PORT', '587');
    $port = filter_var($portValue, FILTER_VALIDATE_INT);
    if ($port === false || $port < 1 || $port > 65535) {
        $missing[] = 'SMTP_PORT (valid port required)';
    }

    $encryption = strtolower(trim((string) app_env('SMTP_ENCRYPTION', 'tls')));
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        $missing[] = 'SMTP_ENCRYPTION (use tls or ssl)';
    }

    if ($missing !== []) {
        error_log('Appointment email delivery is not configured. Check: ' . implode(', ', $missing));
        return null;
    }

    return [
        'host' => trim($required['SMTP_HOST']),
        'username' => trim($required['SMTP_USERNAME']),
        'password' => $required['SMTP_PASSWORD'],
        'from_email' => trim($fromEmail),
        'from_name' => trim((string) app_env('SMTP_FROM_NAME', 'Stomatološka ordinacija')),
        'port' => $port,
        'encryption' => $encryption,
    ];
}

function appointment_email_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function appointment_status_label(string $status, string $locale): string
{
    if ($status === 'pending') {
        return appointment_i18n($locale, 'status_pending');
    }

    if ($status === 'confirmed') {
        return appointment_i18n($locale, 'status_confirmed');
    }

    if ($status === 'cancelled') {
        return appointment_i18n($locale, 'status_cancelled');
    }

    return appointment_i18n($locale, 'status_unknown');
}

function build_appointment_email(array $booking, string $recipientType): array
{
    $locale = $recipientType === 'clinic'
        ? 'sr-Latn'
        : appointment_normalize_locale($booking['language'] ?? null);
    $dateTime = new DateTimeImmutable($booking['appointment_start'], new DateTimeZone('Europe/Belgrade'));
    $date = $dateTime->format('d.m.Y.');
    $time = $dateTime->format('H:i');
    $status = appointment_status_label((string) $booking['status'], $locale);
    $pendingNote = $booking['status'] === 'pending'
        ? appointment_i18n($locale, 'pending_note')
        : '';

    $rows = [
        appointment_i18n($locale, 'request_number') => '#' . (int) $booking['appointment_id'],
        appointment_i18n($locale, 'clinic') => $booking['clinic_name'],
        appointment_i18n($locale, 'service') => $booking['service_name'],
        appointment_i18n($locale, 'date') => $date,
        appointment_i18n($locale, 'appointment_time') => $time,
        appointment_i18n($locale, 'status') => $status,
    ];

    if ($recipientType === 'clinic') {
        $rows = [
            appointment_i18n($locale, 'request_number') => '#' . (int) $booking['appointment_id'],
            appointment_i18n($locale, 'patient') => $booking['patient_name'],
            appointment_i18n($locale, 'patient_email') => $booking['patient_email'],
            appointment_i18n($locale, 'patient_phone') => $booking['patient_phone'],
            appointment_i18n($locale, 'service') => $booking['service_name'],
            appointment_i18n($locale, 'date') => $date,
            appointment_i18n($locale, 'appointment_time') => $time,
            appointment_i18n($locale, 'status') => $status,
        ];
        $greeting = appointment_i18n($locale, 'clinic_greeting');
        $note = $pendingNote;
        $intro = '';
    } else {
        $greeting = str_replace(
            '{name}',
            (string) $booking['patient_name'],
            appointment_i18n($locale, 'customer_greeting')
        );
        $note = $pendingNote;
        $intro = appointment_i18n($locale, 'customer_intro');
        if (!empty($booking['clinic_address'])) {
            $rows[appointment_i18n($locale, 'clinic_address')] = $booking['clinic_address'];
        }
        if (!empty($booking['clinic_phone'])) {
            $rows[appointment_i18n($locale, 'clinic_phone')] = $booking['clinic_phone'];
        }
        if (!empty($booking['clinic_email'])) {
            $rows[appointment_i18n($locale, 'clinic_email')] = $booking['clinic_email'];
        }
    }

    $htmlRows = '';
    $textRows = [];
    foreach ($rows as $label => $value) {
        $htmlRows .= '<tr>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #e8edf0;color:#71808a;font-size:14px;">'
            . appointment_email_escape($label)
            . '</td><td style="padding:10px 12px;border-bottom:1px solid #e8edf0;color:#171c22;font-size:14px;font-weight:600;">'
            . appointment_email_escape($value)
            . '</td></tr>';
        $textRows[] = $label . ': ' . $value;
    }

    $htmlGreeting = appointment_email_escape($greeting);
    $htmlIntro = $intro === '' ? '' : '<p style="margin:8px 0 0;color:#52616b;font-size:15px;line-height:1.6;">'
        . appointment_email_escape($intro)
        . '</p>';
    $htmlNote = $note === '' ? '' : '<p style="margin:18px 0 0;padding:12px 14px;border-radius:6px;background:#eef6f7;color:#204a56;font-size:14px;line-height:1.6;">'
        . appointment_email_escape($note)
        . '</p>';
    $html = '<!doctype html><html lang="' . appointment_email_escape($locale) . '"><body style="margin:0;padding:0;background:#f2f5f6;font-family:Arial,Helvetica,sans-serif;color:#171c22;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5f6;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e0e6e9;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="padding:22px 26px;background:#204a56;color:#ffffff;font-size:18px;font-weight:700;">'
        . appointment_email_escape($booking['clinic_name'])
        . '</td></tr><tr><td style="padding:26px;">'
        . '<h1 style="margin:0 0 12px;color:#171c22;font-size:22px;line-height:1.3;">'
        . appointment_email_escape(appointment_i18n($locale, 'email_title'))
        . '</h1>'
        . '<p style="margin:0;color:#52616b;font-size:15px;line-height:1.6;">' . $htmlGreeting . '</p>'
        . $htmlIntro
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;border-collapse:collapse;">'
        . $htmlRows
        . '</table>' . $htmlNote
        . '<p style="margin:24px 0 0;color:#7a8593;font-size:12px;line-height:1.6;">'
        . appointment_email_escape(appointment_i18n($locale, 'email_footer'))
        . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';

    $text = $greeting . "\n\n"
        . ($intro === '' ? '' : $intro . "\n\n")
        . implode("\n", $textRows)
        . ($note === '' ? '' : "\n\n" . $note)
        . "\n\n" . appointment_i18n($locale, 'email_footer');

    return ['html' => $html, 'text' => $text];
}

function send_appointment_email(array $config, array $booking, string $recipient, string $recipientName, string $recipientType): bool
{
    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Port = $config['port'];
        $mailer->SMTPSecure = $config['encryption'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 15;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->addAddress($recipient, $recipientName);

        if (
            $recipientType === 'customer' &&
            filter_var((string) ($booking['clinic_email'] ?? ''), FILTER_VALIDATE_EMAIL)
        ) {
            $mailer->addReplyTo($booking['clinic_email'], $booking['clinic_name']);
        }

        $mailer->isHTML(true);
        $locale = $recipientType === 'clinic'
            ? 'sr-Latn'
            : appointment_normalize_locale($booking['language'] ?? null);
        $mailer->Subject = $recipientType === 'customer'
            ? appointment_i18n($locale, 'customer_subject')
            : 'Novi zahtev za pregled br. ' . (int) $booking['appointment_id'];
        $content = build_appointment_email($booking, $recipientType);
        $mailer->Body = $content['html'];
        $mailer->AltBody = $content['text'];

        return $mailer->send();
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        error_log(
            'Appointment email delivery failed. Appointment ID '
            . (int) $booking['appointment_id']
            . ', recipient type ' . $recipientType
            . ', error type ' . get_class($exception)
            . ': ' . preg_replace(
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '[email]',
                $exception->getMessage()
            )
        );
        return false;
    }
}

function send_appointment_notifications(array $booking): array
{
    $result = [
        'customer' => 'not_configured',
        'clinic' => 'not_configured',
    ];
    $config = get_appointment_mail_config();
    if ($config === null) {
        return $result;
    }

    $result['customer'] = send_appointment_email(
        $config,
        $booking,
        $booking['patient_email'],
        $booking['patient_name'],
        'customer'
    ) ? 'sent' : 'failed';

    $clinicEmail = trim((string) ($booking['clinic_email'] ?? ''));
    if (filter_var($clinicEmail, FILTER_VALIDATE_EMAIL) === false) {
        error_log('Ordinacija email notification skipped for appointment ID ' . (int) $booking['appointment_id'] . ': no valid clinic email is configured.');
        return $result;
    }

    $result['clinic'] = send_appointment_email(
        $config,
        $booking,
        $clinicEmail,
        $booking['clinic_name'],
        'clinic'
    ) ? 'sent' : 'failed';

    return $result;
}
