-- ---------------------------------------------------------------------------
-- Adresse réelle du concept store
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/007-adresse-du-concept-store.sql
--
-- Le bloc « le lieu » de l'accueil portait déjà la vraie adresse, mais le
-- point de retrait proposé au client dans le tunnel de commande était resté
-- sur l'adresse d'exemple. Une boutique qui annonce deux adresses différentes
-- fait douter de la bonne.
--
-- Inutile sur une nouvelle installation : `database/seed.php` pose déjà la
-- bonne adresse.
-- ---------------------------------------------------------------------------

UPDATE `pickup_points`
   SET `address_line1` = '8 rue Albert Simonin'
 WHERE `address_line1` = '12 rue de la Piscine';
