-- PROTIS IBF V.3 - schema normalizzato
-- Tutte le ore sono memorizzate in MINUTI (interi): niente più colonne TIME dinamiche.

CREATE TABLE IF NOT EXISTS `#__protis_sites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ente` varchar(100) NOT NULL DEFAULT 'CNR',
  `istituto` varchar(255) NOT NULL DEFAULT '',
  `sigla` varchar(50) NOT NULL DEFAULT '',
  `sede` varchar(255) NOT NULL DEFAULT '',
  `codice_sede` varchar(20) NOT NULL COMMENT 'Codice sede ePAS / departmentNumber LDAP',
  `epas_user` text NULL COMMENT 'cifrato (sodium secretbox)',
  `epas_password` text NULL COMMENT 'cifrato (sodium secretbox)',
  `epas_only` tinyint NOT NULL DEFAULT 0 COMMENT '1 = sede di altro istituto usata solo per leggere ePAS (non abilita l\'accesso)',
  `published` tinyint NOT NULL DEFAULT 1,
  `created` datetime NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codice_sede` (`codice_sede`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_people` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NULL COMMENT 'Utente Joomla collegato',
  `uid` varchar(150) NOT NULL COMMENT 'username LDAP / Joomla',
  `matricola` varchar(50) NULL,
  `nome` varchar(150) NOT NULL DEFAULT '',
  `cognome` varchar(150) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `codice_fiscale` varchar(32) NOT NULL DEFAULT '',
  `tipo_contratto` varchar(255) NOT NULL DEFAULT '',
  `data_assunzione` date NULL COMMENT 'per il monte ore lavorativo annuo previsto (1520/1506)',
  `site_id` int unsigned NULL,
  `ldap_sede` varchar(20) NOT NULL DEFAULT '',
  `ldap_gruppo` varchar(255) NOT NULL DEFAULT '',
  `avatar` varchar(100) NOT NULL DEFAULT '' COMMENT 'foto profilo (images/protis/avatar)',
  `sede_esterna` varchar(150) NOT NULL DEFAULT '' COMMENT 'sede del collega di altro istituto',
  `source` varchar(20) NOT NULL DEFAULT 'ldap' COMMENT 'ldap|manual|external',
  `external` tinyint NOT NULL DEFAULT 0 COMMENT '1 = collega di altro istituto abilitato per UID (presenze manuali)',
  `epas_off` tinyint NOT NULL DEFAULT 0 COMMENT '1 = presenze inserite a mano anche se ha la matricola',
  `epas_site_id` int NULL COMMENT 'collega di altro istituto: sede (del suo istituto) da cui leggere ePAS',
  `active` tinyint NOT NULL DEFAULT 1,
  `created` datetime NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid` (`uid`),
  KEY `idx_matricola` (`matricola`),
  KEY `idx_user` (`user_id`),
  KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_projects` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` varchar(30) NULL COMMENT 'ID progetto V.2 (Progetto_YYYYmmddHHiiss)',
  `acronimo` varchar(255) NOT NULL DEFAULT '',
  `titolo` text NULL,
  `codice_avviso` text NULL,
  `cup` varchar(100) NOT NULL DEFAULT '',
  `grant_agreement` varchar(100) NOT NULL DEFAULT '',
  `tipo_ricerca` varchar(150) NOT NULL DEFAULT '' COMMENT 'RI, SS, altro',
  `sede_altro` varchar(255) NOT NULL DEFAULT '' COMMENT 'sede proponente scritta a mano (se non tra le sedi)',
  `ente` varchar(255) NOT NULL DEFAULT '',
  `codice_progetto` text NULL,
  `note` text NULL,
  `data_inizio` date NULL,
  `data_fine` date NULL,
  `data_proroga` date NULL,
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'fascia loghi composta (PNG)',
  `logos` text NULL COMMENT 'JSON: loghi singoli in ordine',
  `header_fields` mediumtext NULL COMMENT 'JSON: righe intestazione timesheet [{label,value}]',
  `table_lang` varchar(2) NOT NULL DEFAULT '' COMMENT 'it|en righe tabella (vuoto = da layout)',
  `layout` varchar(20) NOT NULL DEFAULT 'standard' COMMENT 'standard|horizon|mise|sal',
  `partita_iva` varchar(30) NOT NULL DEFAULT '',
  `sal_numero` varchar(30) NOT NULL DEFAULT '',
  `monte_ore_annuo` varchar(30) NOT NULL DEFAULT '',
  `site_id` int unsigned NULL COMMENT 'sede proponente (NULL = tutte)',
  `published` tinyint NOT NULL DEFAULT 1,
  `created` datetime NULL,
  `created_by` int NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cup` (`cup`),
  KEY `idx_legacy` (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_workpackages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int unsigned NOT NULL,
  `code` varchar(30) NOT NULL DEFAULT '' COMMENT 'es. wp_1',
  `label` varchar(500) NOT NULL DEFAULT '',
  `ordering` int NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `fk_protis_wp_project` FOREIGN KEY (`project_id`) REFERENCES `#__protis_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `project_id` int unsigned NOT NULL,
  `monteore_min` int NULL COMMENT 'ore previste sul progetto da rendicontare (minuti)',
  `monte_annuo_min` int NULL COMMENT 'monte ore annuo della persona sul progetto (minuti)',
  `layout` varchar(20) NOT NULL DEFAULT '' COMMENT 'override layout timesheet (vuoto = da progetto)',
  `nota` varchar(500) NOT NULL DEFAULT '',
  `created` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_person_project` (`person_id`, `project_id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `fk_protis_as_person` FOREIGN KEY (`person_id`) REFERENCES `#__protis_people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_protis_as_project` FOREIGN KEY (`project_id`) REFERENCES `#__protis_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una riga per persona/giorno: presenze ePAS o inserimento manuale
CREATE TABLE IF NOT EXISTS `#__protis_days` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `day` date NOT NULL,
  `worked_min` int NOT NULL DEFAULT 0,
  `smart_min` int NOT NULL DEFAULT 0,
  `tele_min` int NOT NULL DEFAULT 0,
  `mission_min` int NOT NULL DEFAULT 0,
  `sick_min` int NOT NULL DEFAULT 0,
  `holiday_min` int NOT NULL DEFAULT 0,
  `permit_min` int NOT NULL DEFAULT 0,
  `other_min` int NOT NULL DEFAULT 0,
  `absence_codes` varchar(255) NOT NULL DEFAULT '',
  `blocked` tinyint NOT NULL DEFAULT 0 COMMENT '1 = giornata esclusa da riempimento/distribuzione automatica',
  `source` varchar(10) NOT NULL DEFAULT 'manual' COMMENT 'epas|manual|v2',
  `epas_raw` mediumtext NULL,
  `synced` datetime NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_person_day` (`person_id`, `day`),
  KEY `idx_day` (`day`),
  CONSTRAINT `fk_protis_day_person` FOREIGN KEY (`person_id`) REFERENCES `#__protis_people` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ore rendicontate per persona/giorno/WP
CREATE TABLE IF NOT EXISTS `#__protis_hours` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `day` date NOT NULL,
  `wp_id` int unsigned NOT NULL,
  `minutes` int NOT NULL DEFAULT 0,
  `modified` datetime NULL,
  `modified_by` int NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_person_day_wp` (`person_id`, `day`, `wp_id`),
  KEY `idx_wp_day` (`wp_id`, `day`),
  CONSTRAINT `fk_protis_h_person` FOREIGN KEY (`person_id`) REFERENCES `#__protis_people` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_protis_h_wp` FOREIGN KEY (`wp_id`) REFERENCES `#__protis_workpackages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_absence_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `description` text NULL,
  `category` varchar(20) NOT NULL DEFAULT 'altro' COMMENT 'smartworking|telelavoro|missione|malattia|ferie|permessi|altro',
  `minutes` int NULL COMMENT 'orario convenzionale in minuti (NULL = non conteggiato)',
  `blocks_fill` tinyint NOT NULL DEFAULT 0 COMMENT 'esclude il giorno da riempimento/distribuzione',
  `is_new` tinyint NOT NULL DEFAULT 0 COMMENT '1 = scaricato da ePAS, categoria e orario da completare',
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_contract_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ente` varchar(100) NOT NULL DEFAULT 'CNR',
  `label` varchar(255) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__protis_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `user_id` int NULL,
  `person_id` int unsigned NULL,
  `action` varchar(50) NOT NULL,
  `message` text NULL,
  PRIMARY KEY (`id`),
  KEY `idx_person` (`person_id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__protis_contract_types` (`id`, `ente`, `label`, `ordering`) VALUES
(1, 'CNR', 'Collaboratore Tecnico E.R. IV Livello', 1),
(2, 'CNR', 'Collaboratore Tecnico E.R. V Livello', 2),
(3, 'CNR', 'Collaboratore Tecnico E.R. VI Livello', 3),
(4, 'CNR', 'Collaboratore di Amministrazione IV Livello', 4),
(5, 'CNR', 'Collaboratore di Amministrazione V Livello', 5),
(6, 'CNR', 'Collaboratore di Amministrazione VI Livello', 6),
(7, 'CNR', 'Funzionario di Amministrazione IV Livello', 7),
(8, 'CNR', 'Funzionario di Amministrazione V Livello', 8),
(9, 'CNR', 'Ricercatore, III Livello', 9),
(10, 'CNR', 'Primo Ricercatore, II Livello', 10),
(11, 'CNR', 'Dirigente di Ricerca, I Livello', 11),
(12, 'CNR', 'Tecnologo, III Livello', 12),
(13, 'CNR', 'Primo Tecnologo, II Livello', 13),
(14, 'CNR', 'Dirigente Tecnologo, I Livello', 14),
(15, 'CNR', 'Direttore di Istituto', 15),
(16, 'CNR', 'Assegnista di ricerca', 16),
(17, 'CNR', 'Borsista', 17),
(18, 'CNR', 'Dottorando', 18);

-- Le sedi (codice sede = departmentNumber LDAP) si inseriscono da PROTIS IBF → Impostazioni dopo l'installazione.
