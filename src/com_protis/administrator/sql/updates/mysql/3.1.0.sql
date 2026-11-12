-- 3.1.0 intestazione timesheet personalizzabile e loghi multipli
ALTER TABLE `#__protis_projects` ADD COLUMN `logos` text NULL COMMENT 'JSON: loghi singoli in ordine' AFTER `logo`;
ALTER TABLE `#__protis_projects` ADD COLUMN `header_fields` mediumtext NULL COMMENT 'JSON: righe intestazione timesheet [{label,value}]' AFTER `logos`;
ALTER TABLE `#__protis_projects` ADD COLUMN `table_lang` varchar(2) NOT NULL DEFAULT '' AFTER `header_fields`;
