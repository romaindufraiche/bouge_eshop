-- ---------------------------------------------------------------------------
-- Comptes clients et suivi de livraison
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/002-comptes-clients.sql
--
-- Inutile sur une nouvelle installation : `database/schema.sql` les contient.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `phone`         VARCHAR(30) DEFAULT NULL,
  `address_line1` VARCHAR(200) DEFAULT NULL,
  `address_line2` VARCHAR(200) DEFAULT NULL,
  `postal_code`   VARCHAR(10) DEFAULT NULL,
  `city`          VARCHAR(120) DEFAULT NULL,
  `cart`          TEXT DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `orders`
  ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `pickup_point_id`,
  ADD COLUMN `tracking_carrier` VARCHAR(60) DEFAULT NULL AFTER `customer_id`,
  ADD COLUMN `tracking_number` VARCHAR(80) DEFAULT NULL AFTER `tracking_carrier`,
  ADD COLUMN `shipped_at` DATETIME DEFAULT NULL AFTER `tracking_number`,
  ADD KEY `orders_customer` (`customer_id`),
  ADD CONSTRAINT `orders_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
