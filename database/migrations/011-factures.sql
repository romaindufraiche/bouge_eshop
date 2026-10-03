-- Numérotation des factures
--
-- Une facture doit porter un numéro séquentiel et sans rupture. La référence
-- de commande (BG-XXXXXX) est tirée au sort pour être lisible au téléphone :
-- elle ne peut pas servir. L'identifiant de commande, lui, saute dès qu'un
-- panier est abandonné au paiement — or une facture n'est émise que pour une
-- commande payée.
--
-- Le numéro est donc attribué à la première édition de la facture, et retenu :
-- rééditer le même document ne consomme pas un second numéro.
--
-- À jouer sur une base déjà installée :
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/011-factures.sql

ALTER TABLE `orders`
  ADD COLUMN `invoice_number` INT UNSIGNED DEFAULT NULL AFTER `paid_at`,
  ADD COLUMN `invoiced_at` DATETIME DEFAULT NULL AFTER `invoice_number`,
  ADD UNIQUE KEY `orders_invoice_number` (`invoice_number`);
