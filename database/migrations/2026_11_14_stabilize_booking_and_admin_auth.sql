-- Run after the existing admin_users and legacy clinic schema migrations.
-- Existing legacy admin password hashes are copied without changing them.

ALTER TABLE `admin_users`
  ADD COLUMN `clinic_id` int(10) unsigned DEFAULT NULL AFTER `id`,
  ADD KEY `idx_admin_users_clinic_id` (`clinic_id`),
  ADD CONSTRAINT `fk_admin_users_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
    ON DELETE SET NULL;

UPDATE `admin_users` AS au
INNER JOIN `admins` AS a ON a.`email` = au.`email`
SET au.`clinic_id` = a.`clinic_id`
WHERE au.`clinic_id` IS NULL;

INSERT INTO `admin_users` (`clinic_id`, `username`, `email`, `password_hash`, `is_active`)
SELECT
  a.`clinic_id`,
  CONCAT('legacy_', LOWER(SUBSTRING(SHA2(CONCAT(a.`id`, ':', a.`email`), 256), 1, 32))),
  a.`email`,
  a.`password_hash`,
  1
FROM `admins` AS a
WHERE NOT EXISTS (
  SELECT 1 FROM `admin_users` AS au WHERE au.`email` = a.`email`
);

ALTER TABLE `appointments`
  ADD COLUMN `service_duration_minutes` smallint unsigned NOT NULL DEFAULT 30 AFTER `service_id`;

UPDATE `appointments` AS a
LEFT JOIN `services` AS s ON s.`id` = a.`service_id`
SET a.`service_duration_minutes` = COALESCE(NULLIF(s.`duration_minutes`, 0), 30);
