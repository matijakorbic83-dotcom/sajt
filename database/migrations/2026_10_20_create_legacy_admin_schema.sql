CREATE DATABASE IF NOT EXISTS `dental_booking`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `dental_booking`;

CREATE TABLE IF NOT EXISTS `clinics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_clinic_id` (`clinic_id`),
  CONSTRAINT `fk_admins_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` int(10) unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` smallint unsigned NOT NULL DEFAULT 30,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_services_clinic_active` (`clinic_id`, `active`),
  CONSTRAINT `fk_services_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `patient_name` varchar(190) NOT NULL,
  `patient_email` varchar(190) NOT NULL,
  `patient_phone` varchar(40) NOT NULL,
  `appointment_start` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_appointments_clinic_status_start` (`clinic_id`, `status`, `appointment_start`),
  KEY `idx_appointments_service_id` (`service_id`),
  CONSTRAINT `fk_appointments_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_service`
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clinics` (`name`)
SELECT 'SmileCraft Dental'
WHERE NOT EXISTS (
  SELECT 1 FROM `clinics` WHERE `name` = 'SmileCraft Dental'
);

INSERT INTO `services` (`clinic_id`, `name`, `description`, `duration_minutes`, `active`)
SELECT `id`, 'Stomatološki pregled', 'Početna usluga za zakazivanje.', 30, 1
FROM `clinics`
WHERE `name` = 'SmileCraft Dental'
  AND NOT EXISTS (
    SELECT 1 FROM `services`
    WHERE `clinic_id` = `clinics`.`id`
      AND `name` = 'Stomatološki pregled'
  )
LIMIT 1;
