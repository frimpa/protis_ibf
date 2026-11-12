-- 3.15.0: monte ore annuo per associazione persona/progetto
ALTER TABLE `#__protis_assignments` ADD COLUMN `monte_annuo_min` int NULL AFTER `monteore_min`;
