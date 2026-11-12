-- 3.8.0: codici di assenza scaricati da ePAS e segnalati come nuovi
ALTER TABLE `#__protis_absence_codes` ADD COLUMN `is_new` tinyint NOT NULL DEFAULT 0 AFTER `blocks_fill`;
