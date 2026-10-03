-- Courriel d'expédition
--
-- La boutique confirmait la commande puis ne disait plus rien : le client
-- n'apprenait le départ du colis que par le transporteur, ou pas du tout.
-- Cette colonne retient la date d'envoi de l'avis d'expédition, pour qu'il ne
-- parte qu'une fois — corriger un numéro de suivi ne doit pas déclencher un
-- second message.
--
-- À jouer sur une base déjà installée :
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/009-courriel-expedition.sql

ALTER TABLE `orders`
  ADD COLUMN `shipping_email_sent_at` DATETIME NULL DEFAULT NULL AFTER `shipped_at`;
