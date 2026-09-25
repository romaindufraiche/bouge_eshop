-- ---------------------------------------------------------------------------
-- Livraison en point relais
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/005-point-relais.sql
--
-- Trois modes de remise au lieu de deux : livraison à domicile, livraison en
-- point relais du transporteur, retrait au concept store. Le point relais
-- n'est pas un point de retrait de la boutique : il appartient au
-- transporteur, il change à chaque commande, et il est choisi par le client.
-- Il est donc recopié sur la commande plutôt que rangé dans une table à part,
-- exactement comme les libellés et les prix des articles achetés.
--
-- Inutile sur une nouvelle installation : `database/schema.sql` contient déjà
-- ces colonnes.
-- ---------------------------------------------------------------------------

ALTER TABLE `orders`
  -- Identifiant du point chez le transporteur, celui qu'il faut lui redonner
  -- au moment d'acheter l'étiquette.
  ADD COLUMN `relay_code` VARCHAR(40) DEFAULT NULL AFTER `pickup_point_id`,
  -- Transporteur du point : Mondial Relay, Relais Colis, Chronopost…
  ADD COLUMN `relay_operator` VARCHAR(40) DEFAULT NULL AFTER `relay_code`,
  -- L'adresse est recopiée pour rester lisible dans dix ans, même si le point
  -- a fermé entre-temps.
  ADD COLUMN `relay_name` VARCHAR(160) DEFAULT NULL AFTER `relay_operator`,
  ADD COLUMN `relay_address` VARCHAR(200) DEFAULT NULL AFTER `relay_name`,
  ADD COLUMN `relay_postal_code` VARCHAR(10) DEFAULT NULL AFTER `relay_address`,
  ADD COLUMN `relay_city` VARCHAR(120) DEFAULT NULL AFTER `relay_postal_code`,
  -- Étiquette achetée auprès du transporteur : de quoi la réimprimer sans la
  -- racheter.
  ADD COLUMN `label_url` VARCHAR(500) DEFAULT NULL AFTER `shipped_at`,
  ADD COLUMN `label_reference` VARCHAR(80) DEFAULT NULL AFTER `label_url`;

ALTER TABLE `products`
  -- Poids unitaire en grammes. Le transporteur facture au poids ; sans cette
  -- valeur, le poids par défaut de `config/shop.php` s'applique.
  ADD COLUMN `weight_grams` INT UNSIGNED DEFAULT NULL AFTER `stock`;

ALTER TABLE `order_items`
  -- Poids recopié au moment de l'achat, comme le libellé et le prix : la
  -- commande doit rester expédiable même si le produit quitte le catalogue.
  ADD COLUMN `weight_grams` INT UNSIGNED DEFAULT NULL AFTER `unit_price_cents`;
