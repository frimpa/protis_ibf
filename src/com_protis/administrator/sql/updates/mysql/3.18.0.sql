-- 3.18.0: interruttore presenze ePAS / inserimento manuale per persona; ePAS di altri istituti
ALTER TABLE `#__protis_people` ADD COLUMN `epas_off` tinyint NOT NULL DEFAULT 0 AFTER `external`;
ALTER TABLE `#__protis_people` ADD COLUMN `epas_site_id` int NULL AFTER `epas_off`;
ALTER TABLE `#__protis_sites` ADD COLUMN `epas_only` tinyint NOT NULL DEFAULT 0 AFTER `epas_password`;
