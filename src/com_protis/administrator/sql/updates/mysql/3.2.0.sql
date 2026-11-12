-- 3.2.0 colleghi di altri istituti abilitati per UID
ALTER TABLE `#__protis_people` ADD COLUMN `external` tinyint NOT NULL DEFAULT 0 AFTER `source`;
