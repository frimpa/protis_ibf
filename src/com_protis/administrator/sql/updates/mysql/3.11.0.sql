-- 3.11.0: sede dei colleghi di altri istituti
ALTER TABLE `#__protis_people` ADD COLUMN `sede_esterna` varchar(150) NOT NULL DEFAULT '' AFTER `ldap_gruppo`;
