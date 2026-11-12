-- 3.10.0: sede proponente scritta a mano
ALTER TABLE `#__protis_projects` ADD COLUMN `sede_altro` varchar(255) NOT NULL DEFAULT '' AFTER `grant_agreement`;
