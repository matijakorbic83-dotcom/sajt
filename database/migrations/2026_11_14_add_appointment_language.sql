ALTER TABLE `appointments`
  ADD COLUMN `language` varchar(10) NOT NULL DEFAULT 'sr-Latn' AFTER `patient_phone`;

UPDATE `appointments`
SET `language` = 'sr-Latn';
