CREATE DATABASE IF NOT EXISTS `dental_booking`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `dental_booking`;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_username` (`username`),
  UNIQUE KEY `uq_admin_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
