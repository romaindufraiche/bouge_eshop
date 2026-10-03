-- Taux de TVA par produit
--
-- Une facture avec un taux de TVA faux ne vaut rien. La boutique vend du
-- matériel de natation à 20 % et un livre à 5,5 % : un taux unique aurait
-- donné une facture inexacte sur l'un des deux.
--
-- Le taux est en points de base (2000 = 20,00 %), comme les prix sont en
-- centimes : des entiers partout, aucun flottant dans un calcul d'argent.
--
-- Il est recopié sur la ligne de commande au moment de l'achat, comme le
-- prix et le poids : changer le taux d'un produit ne doit pas réécrire les
-- factures déjà émises.
--
-- À jouer sur une base déjà installée :
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/010-tva.sql

ALTER TABLE `products`
  ADD COLUMN `vat_rate_bp` SMALLINT UNSIGNED NOT NULL DEFAULT 2000 AFTER `price_cents`;

ALTER TABLE `order_items`
  ADD COLUMN `vat_rate_bp` SMALLINT UNSIGNED NOT NULL DEFAULT 2000 AFTER `unit_price_cents`;

-- Le livre passe au taux réduit des ouvrages.
UPDATE `products` p
   JOIN `categories` c ON c.id = p.category_id
   SET p.vat_rate_bp = 550
 WHERE c.slug = 'livre';
