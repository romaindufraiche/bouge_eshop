-- ---------------------------------------------------------------------------
-- Navigation par usage
--
-- À jouer sur une base déjà installée, depuis phpMyAdmin ou en ligne de
-- commande :
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/001-usages.sql
--
-- Inutile sur une nouvelle installation : `database/schema.sql` contient déjà
-- la colonne.
-- ---------------------------------------------------------------------------

ALTER TABLE `products`
  ADD COLUMN `usages` VARCHAR(190) DEFAULT NULL AFTER `available_in_store`;
