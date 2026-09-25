-- ---------------------------------------------------------------------------
-- Marquage des produits de démonstration
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/003-source-demo.sql
--
-- Inutile sur une nouvelle installation : `database/schema.sql` contient déjà
-- la colonne.
-- ---------------------------------------------------------------------------

ALTER TABLE `products`
  ADD COLUMN `demo_source` VARCHAR(60) DEFAULT NULL AFTER `usages`,
  ADD KEY `products_demo` (`demo_source`);
