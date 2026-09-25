-- ---------------------------------------------------------------------------
-- La catégorie « Vêtements » devient « Maillots »
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/004-maillots.sql
--
-- Le rayon ne contient que des maillots et des jammers : le mot exact dit
-- mieux ce qu'on y trouve, et c'est celui que les nageurs tapent.
--
-- Inutile sur une nouvelle installation : `database/seed.php` crée déjà la
-- catégorie sous son nouveau nom.
--
-- L'adresse de la catégorie change avec son identifiant : /boutique/vetements
-- devient /boutique/maillots. Sans conséquence tant que le site n'est pas en
-- ligne ; s'il l'est déjà, prévoir une redirection dans `public/.htaccess`.
-- ---------------------------------------------------------------------------

UPDATE `categories`
   SET `name` = 'Maillots',
       `slug` = 'maillots'
 WHERE `slug` = 'vetements';

-- Les visuels provisoires du jeu de démonstration suivent le même nom.
UPDATE `product_images`
   SET `url` = REPLACE(`url`, 'demo/vetements.svg', 'demo/maillots.svg')
 WHERE `url` LIKE '%demo/vetements.svg%';

UPDATE `product_images`
   SET `alt` = REPLACE(`alt`, 'Vêtements', 'Maillots')
 WHERE `alt` LIKE '%Vêtements%';

UPDATE `categories`
   SET `description` = 'Maillots et jammers résistants au chlore.'
 WHERE `slug` = 'maillots'
   AND `description` = 'Maillots et textile résistants au chlore.';
