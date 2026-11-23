USE `dental_booking`;

CREATE TABLE IF NOT EXISTS `clinic_opening_hours` (
  `clinic_id` int(10) unsigned NOT NULL,
  `weekday` tinyint unsigned NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 1,
  `opens_at` time DEFAULT NULL,
  `closes_at` time DEFAULT NULL,
  `break_starts_at` time DEFAULT NULL,
  `break_ends_at` time DEFAULT NULL,
  PRIMARY KEY (`clinic_id`, `weekday`),
  CONSTRAINT `fk_clinic_opening_hours_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clinic_opening_hours`
  (`clinic_id`, `weekday`, `is_closed`, `opens_at`, `closes_at`, `break_starts_at`, `break_ends_at`)
SELECT
  c.`id`,
  weekdays.`weekday`,
  CASE WHEN weekdays.`weekday` BETWEEN 1 AND 5 THEN 0 ELSE 1 END,
  CASE WHEN weekdays.`weekday` BETWEEN 1 AND 5 THEN '09:00:00' ELSE NULL END,
  CASE WHEN weekdays.`weekday` BETWEEN 1 AND 5 THEN '17:00:00' ELSE NULL END,
  CASE WHEN weekdays.`weekday` BETWEEN 1 AND 5 THEN '13:00:00' ELSE NULL END,
  CASE WHEN weekdays.`weekday` BETWEEN 1 AND 5 THEN '14:00:00' ELSE NULL END
FROM `clinics` c
CROSS JOIN (
  SELECT 1 AS `weekday` UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
) AS weekdays
WHERE NOT EXISTS (
  SELECT 1 FROM `clinic_opening_hours` existing
  WHERE existing.`clinic_id` = c.`id`
    AND existing.`weekday` = weekdays.`weekday`
);

CREATE TABLE IF NOT EXISTS `clinic_content_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinic_id` int(10) unsigned NOT NULL,
  `content_type` enum('dentist','review','gallery','faq') NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` text DEFAULT NULL,
  `media_url` varchar(2048) DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clinic_content_type_order` (`clinic_id`, `content_type`, `sort_order`, `id`),
  CONSTRAINT `fk_clinic_content_items_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
