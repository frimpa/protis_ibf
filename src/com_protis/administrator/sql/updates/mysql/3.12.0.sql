-- 3.12.0: tipo di ricerca del progetto (Ricerca Industriale, Sviluppo Sperimentale, altro)
ALTER TABLE `#__protis_projects` ADD COLUMN `tipo_ricerca` varchar(150) NOT NULL DEFAULT '' AFTER `grant_agreement`;
