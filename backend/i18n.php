<?php

declare(strict_types=1);

function appointment_normalize_locale($locale): string
{
    $allowedLocales = ['sr-Latn', 'sr-Cyrl', 'en', 'ru', 'de'];
    if (!is_string($locale)) {
        return 'sr-Latn';
    }

    $locale = trim($locale);

    return in_array($locale, $allowedLocales, true) ? $locale : 'sr-Latn';
}

function appointment_i18n(string $locale, string $key): string
{
    $locale = appointment_normalize_locale($locale);
    $translations = [
        'sr-Latn' => [
            'request_number' => 'Broj zahteva',
            'clinic' => 'Ordinacija',
            'service' => 'Usluga',
            'date' => 'Datum',
            'appointment_time' => 'Termin',
            'status' => 'Status',
            'patient' => 'Pacijent',
            'patient_email' => 'Imejl pacijenta',
            'patient_phone' => 'Telefon pacijenta',
            'clinic_address' => 'Adresa ordinacije',
            'clinic_phone' => 'Telefon ordinacije',
            'clinic_email' => 'Imejl ordinacije',
            'status_pending' => 'Na čekanju — čeka potvrdu ordinacije',
            'status_confirmed' => 'Potvrđen',
            'status_cancelled' => 'Otkazan',
            'status_unknown' => 'Nepoznat',
            'pending_note' => 'Zahtev je primljen i čeka potvrdu. Termin još nije potvrđen.',
            'clinic_greeting' => 'Primljen je novi zahtev za pregled.',
            'customer_greeting' => 'Poštovani/a {name},',
            'customer_intro' => 'U nastavku su detalji vašeg zahteva za pregled.',
            'email_title' => 'Potvrda zahteva za pregled',
            'email_footer' => 'Ova poruka sadrži podatke o pregledu namenjene primaocu. Obratite se ordinaciji ako je potrebno ispraviti neki podatak.',
            'customer_subject' => 'Potvrda zahteva za zakazivanje',
        ],
        'sr-Cyrl' => [
            'request_number' => 'Број захтева',
            'clinic' => 'Ординација',
            'service' => 'Услуга',
            'date' => 'Датум',
            'appointment_time' => 'Термин',
            'status' => 'Статус',
            'patient' => 'Пацијент',
            'patient_email' => 'Имејл пацијента',
            'patient_phone' => 'Телефон пацијента',
            'clinic_address' => 'Адреса ординације',
            'clinic_phone' => 'Телефон ординације',
            'clinic_email' => 'Имејл ординације',
            'status_pending' => 'На чекању — чека потврду ординације',
            'status_confirmed' => 'Потврђен',
            'status_cancelled' => 'Отказан',
            'status_unknown' => 'Непознат',
            'pending_note' => 'Захтев је примљен и чека потврду. Термин још није потврђен.',
            'clinic_greeting' => 'Примљен је нови захтев за преглед.',
            'customer_greeting' => 'Поштовани/а {name},',
            'customer_intro' => 'У наставку су детаљи вашег захтева за преглед.',
            'email_title' => 'Потврда захтева за преглед',
            'email_footer' => 'Ова порука садржи податке о прегледу намењене примаоцу. Обратите се ординацији ако је потребно исправити неки податак.',
            'customer_subject' => 'Потврда захтева за заказивање',
        ],
        'en' => [
            'request_number' => 'Request number',
            'clinic' => 'Clinic',
            'service' => 'Service',
            'date' => 'Date',
            'appointment_time' => 'Time',
            'status' => 'Status',
            'patient' => 'Patient',
            'patient_email' => 'Patient email',
            'patient_phone' => 'Patient phone',
            'clinic_address' => 'Clinic address',
            'clinic_phone' => 'Clinic phone',
            'clinic_email' => 'Clinic email',
            'status_pending' => 'Pending — awaiting clinic confirmation',
            'status_confirmed' => 'Confirmed',
            'status_cancelled' => 'Cancelled',
            'status_unknown' => 'Unknown',
            'pending_note' => 'Your request has been received and is awaiting confirmation. The appointment is not confirmed yet.',
            'clinic_greeting' => 'A new appointment request has been received.',
            'customer_greeting' => 'Dear {name},',
            'customer_intro' => 'Here are the details of your appointment request.',
            'email_title' => 'Appointment request confirmation',
            'email_footer' => 'This message contains appointment details intended for the recipient. Contact the clinic if any information needs to be corrected.',
            'customer_subject' => 'Appointment request confirmation',
        ],
        'ru' => [
            'request_number' => 'Номер заявки',
            'clinic' => 'Клиника',
            'service' => 'Услуга',
            'date' => 'Дата',
            'appointment_time' => 'Время',
            'status' => 'Статус',
            'patient' => 'Пациент',
            'patient_email' => 'Электронная почта пациента',
            'patient_phone' => 'Телефон пациента',
            'clinic_address' => 'Адрес клиники',
            'clinic_phone' => 'Телефон клиники',
            'clinic_email' => 'Электронная почта клиники',
            'status_pending' => 'Ожидает подтверждения клиники',
            'status_confirmed' => 'Подтверждена',
            'status_cancelled' => 'Отменена',
            'status_unknown' => 'Неизвестен',
            'pending_note' => 'Заявка получена и ожидает подтверждения. Визит пока не подтверждён.',
            'clinic_greeting' => 'Получена новая заявка на приём.',
            'customer_greeting' => 'Уважаемый(ая) {name},',
            'customer_intro' => 'Детали вашей заявки на приём:',
            'email_title' => 'Подтверждение заявки на приём',
            'email_footer' => 'Это сообщение содержит данные о визите, предназначенные получателю. Свяжитесь с клиникой, если нужно исправить какую-либо информацию.',
            'customer_subject' => 'Подтверждение заявки на приём',
        ],
        'de' => [
            'request_number' => 'Anfragenummer',
            'clinic' => 'Praxis',
            'service' => 'Leistung',
            'date' => 'Datum',
            'appointment_time' => 'Uhrzeit',
            'status' => 'Status',
            'patient' => 'Patient/in',
            'patient_email' => 'E-Mail des Patienten',
            'patient_phone' => 'Telefon des Patienten',
            'clinic_address' => 'Adresse der Praxis',
            'clinic_phone' => 'Telefon der Praxis',
            'clinic_email' => 'E-Mail der Praxis',
            'status_pending' => 'Ausstehend — Bestätigung der Praxis steht aus',
            'status_confirmed' => 'Bestätigt',
            'status_cancelled' => 'Abgesagt',
            'status_unknown' => 'Unbekannt',
            'pending_note' => 'Ihre Anfrage ist eingegangen und wartet auf die Bestätigung. Der Termin ist noch nicht bestätigt.',
            'clinic_greeting' => 'Eine neue Terminanfrage ist eingegangen.',
            'customer_greeting' => 'Guten Tag {name},',
            'customer_intro' => 'Hier finden Sie die Details Ihrer Terminanfrage.',
            'email_title' => 'Bestätigung der Terminanfrage',
            'email_footer' => 'Diese Nachricht enthält Termindaten, die für den Empfänger bestimmt sind. Bitte wenden Sie sich an die Praxis, falls Angaben korrigiert werden müssen.',
            'customer_subject' => 'Bestätigung der Terminanfrage',
        ],
    ];

    return $translations[$locale][$key] ?? $key;
}
