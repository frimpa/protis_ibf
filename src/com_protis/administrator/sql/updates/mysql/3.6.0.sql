-- 3.6.0: Telelavoro come categoria rendicontabile distinta dal Lavoro Agile
ALTER TABLE `#__protis_days` ADD COLUMN `tele_min` int NOT NULL DEFAULT 0 AFTER `smart_min`;

-- i codici ePAS di telelavoro (103, 103RT...) passano dalla categoria Lavoro Agile a Telelavoro
UPDATE `#__protis_absence_codes` SET `category` = 'telelavoro' WHERE `category` = 'smartworking' AND `description` LIKE '%telelavoro%';

-- le giornate già importate con soli codici di telelavoro spostano le ore (la capacità non cambia)
UPDATE `#__protis_days` AS d SET d.`tele_min` = d.`smart_min`, d.`smart_min` = 0
 WHERE d.`smart_min` > 0
   AND EXISTS (SELECT 1 FROM `#__protis_absence_codes` AS c WHERE c.`category` = 'telelavoro' AND FIND_IN_SET(c.`code`, REPLACE(d.`absence_codes`, ' ', '')))
   AND NOT EXISTS (SELECT 1 FROM `#__protis_absence_codes` AS c WHERE c.`category` = 'smartworking' AND FIND_IN_SET(c.`code`, REPLACE(d.`absence_codes`, ' ', '')));
