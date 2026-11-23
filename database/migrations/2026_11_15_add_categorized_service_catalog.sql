-- Add clinic-scoped categories and seed the Serbian dental service catalog.

ALTER TABLE `services`
  ADD COLUMN `category` varchar(100) NOT NULL DEFAULT 'Ostalo' AFTER `clinic_id`,
  ADD KEY `idx_services_clinic_category_active` (`clinic_id`, `category`, `active`);

CREATE TEMPORARY TABLE `seed_service_catalog` (
  `category` varchar(100) NOT NULL,
  `name` varchar(190) NOT NULL,
  `description` text NOT NULL,
  `duration_minutes` smallint unsigned NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `seed_service_catalog` (`category`, `name`, `description`, `duration_minutes`) VALUES
  ('Preventiva i higijena', 'Stomatološki pregled i konsultacije', '1 poseta', 30),
  ('Preventiva i higijena', 'Uklanjanje kamenca i poliranje (peskiranje)', '1 poseta', 45),
  ('Preventiva i higijena', 'Zalivanje fisura i fluorizacija', '1 poseta', 30),
  ('Konzervativa i endodoncija', 'Popravka zuba (kompozitna plomba)', '1 poseta', 45),
  ('Konzervativa i endodoncija', 'Lečenje kanala korena (vađenje živca)', '1 do 3 posete (tokom 1–2 nedelje)', 60),
  ('Oralna hirurgija', 'Rutinsko vađenje zuba', '1 poseta', 30),
  ('Oralna hirurgija', 'Hirurško vađenje impaktiranog umnjaka', '1 poseta (skidanje konaca za 7 dana)', 60),
  ('Oralna hirurgija', 'Apikotomija (operacija vrha korena / ciste)', '1 poseta', 60),
  ('Protetika', 'Izrada krunica i mostova (metalokeramika, cirkonijum)', '2 do 4 posete (ukupno 7–14 dana)', 60),
  ('Protetika', 'Izrada mobilnih proteza (totalne, parcijalne, skeletirane)', '4 do 5 poseta (ukupno 2–3 nedelje)', 45),
  ('Implantologija', 'Ugradnja zubnog implanta', '1 hirurška poseta (po implantu)', 60),
  ('Implantologija', 'Postavljanje krunice na implant', '3 do 6 meseci nakon ugradnje implanta', 45),
  ('Implantologija', 'Sinus lift (nadogradnja vilične kosti)', '1 poseta (zarastanje 4–6 meseci)', 90),
  ('Estetska stomatologija', 'Ordinacijsko izbeljivanje zuba', '1 poseta', 60),
  ('Estetska stomatologija', 'Izrada keramičkih ili kompozitnih faseta (vinira)', '2 posete (ukupno 7–10 dana)', 90),
  ('Ortodoncija', 'Postavljanje fiksne proteze', 'Kontrole na 4–6 nedelja (traje 12–24 meseca)', 90),
  ('Ortodoncija', 'Terapija providnim folijama (alajnerima)', 'Kontrole na 6–8 nedelja (traje 6–18 meseci)', 45),
  ('Parodontologija', 'Kiretaža parodontalnih džepova (obrada desni)', '1 do 4 posete (po kvadrantima vilice)', 60);

UPDATE `services` AS existing
INNER JOIN `seed_service_catalog` AS catalog ON catalog.`name` = existing.`name`
SET existing.`category` = catalog.`category`,
    existing.`description` = catalog.`description`,
    existing.`duration_minutes` = catalog.`duration_minutes`;

INSERT INTO `services` (`clinic_id`, `category`, `name`, `description`, `duration_minutes`, `active`)
SELECT clinics.`id`, catalog.`category`, catalog.`name`, catalog.`description`, catalog.`duration_minutes`, 1
FROM `clinics`
CROSS JOIN `seed_service_catalog` AS catalog
WHERE NOT EXISTS (
  SELECT 1
  FROM `services` AS existing
  WHERE existing.`clinic_id` = clinics.`id`
    AND existing.`name` = catalog.`name`
);

DROP TEMPORARY TABLE `seed_service_catalog`;
