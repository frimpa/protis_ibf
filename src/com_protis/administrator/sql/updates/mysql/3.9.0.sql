-- 3.9.0: CUP e Grant Agreement separati, data di assunzione per il monte ore annuo previsto
ALTER TABLE `#__protis_projects` ADD COLUMN `grant_agreement` varchar(100) NOT NULL DEFAULT '' AFTER `cup`;
ALTER TABLE `#__protis_people` ADD COLUMN `data_assunzione` date NULL AFTER `tipo_contratto`;

-- i "CUP" solo numerici sono in realtà Grant Agreement (es. RADOV 101061694)
UPDATE `#__protis_projects` SET `header_fields` = REPLACE(`header_fields`, '{cup}', '{grant_agreement}') WHERE `cup` REGEXP '^[0-9]+$' AND `header_fields` IS NOT NULL;
UPDATE `#__protis_projects` SET `grant_agreement` = `cup`, `cup` = '' WHERE `cup` REGEXP '^[0-9]+$';
