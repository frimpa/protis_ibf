-- 3.14.0: foto del profilo
ALTER TABLE `#__protis_people` ADD COLUMN `avatar` varchar(100) NOT NULL DEFAULT '' AFTER `ldap_gruppo`;
