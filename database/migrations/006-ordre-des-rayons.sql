-- ---------------------------------------------------------------------------
-- Ordre d'affichage des rayons
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/006-ordre-des-rayons.sql
--
-- Les maillots passent en tête : c'est le rayon le plus vendu, et à quatre
-- colonnes le livre retombait seul sur une seconde rangée. L'ordre vaut pour
-- l'accueil comme pour les menus — une boutique dont la page d'accueil et le
-- menu ne s'accordent pas se lit comme un défaut.
--
-- Inutile sur une nouvelle installation : `database/seed.php` déclare déjà
-- ces positions.
-- ---------------------------------------------------------------------------

UPDATE `categories` SET `position` = 0 WHERE `slug` = 'maillots';
UPDATE `categories` SET `position` = 1 WHERE `slug` = 'bonnets';
UPDATE `categories` SET `position` = 2 WHERE `slug` = 'lunettes';
UPDATE `categories` SET `position` = 3 WHERE `slug` = 'accessoires';
UPDATE `categories` SET `position` = 4 WHERE `slug` = 'livre';
