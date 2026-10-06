-- Lien de suivi pour les commandes passées sans compte
--
-- Le courriel de confirmation proposait « Suivre ma commande » vers
-- /compte/commande/<référence>, qui exige une session client. Or la boutique
-- autorise — et met en avant — la commande sans compte : ces clients-là
-- recevaient un lien qui les renvoyait vers une page de connexion où ils
-- n'avaient rien à saisir.
--
-- Ce jeton, tiré au sort à la création de la commande, ouvre la même fiche
-- sans compte ni mot de passe. Il n'est connu que du destinataire du
-- courriel : la référence seule ne suffit pas, sinon on lirait la commande
-- du voisin en changeant six caractères.
--
-- À jouer sur une base déjà installée :
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/012-suivi-sans-compte.sql

ALTER TABLE `orders`
  ADD COLUMN `tracking_token` CHAR(32) DEFAULT NULL AFTER `reference`,
  ADD UNIQUE KEY `orders_tracking_token` (`tracking_token`);

-- Les commandes déjà en base en reçoivent un, pour que leurs liens marchent
-- aussi. RAND() suffit ici : ce ne sont que des commandes de démonstration,
-- et les suivantes seront tirées par random_bytes().
UPDATE `orders`
   SET `tracking_token` = LOWER(CONCAT(
         SUBSTRING(MD5(RAND()), 1, 16), SUBSTRING(MD5(RAND()), 1, 16)
       ))
 WHERE `tracking_token` IS NULL;
