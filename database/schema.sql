-- ---------------------------------------------------------------------------
-- BOUGE. — schéma de la base
--
-- MySQL 5.7+ ou MariaDB 10.2+, tels qu'on les trouve sur un hébergement
-- mutualisé. À importer une seule fois, depuis phpMyAdmin ou en ligne de
-- commande :
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/schema.sql
--
-- Conventions
--   - Tous les montants sont des ENTIERS en CENTIMES d'euro : aucune erreur
--     d'arrondi possible, et c'est déjà l'unité attendue par Stripe.
--   - Les colonnes de statut sont des VARCHAR et non des ENUM : ajouter une
--     valeur ne demande alors pas de modifier la structure de la table. Les
--     valeurs autorisées sont listées dans src/Support/Status.php.
--   - VARCHAR(190) sur les colonnes indexées en UNIQUE : au-delà, un index
--     utf8mb4 dépasse la limite de 767 octets des anciens MySQL.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --- Compte administrateur --------------------------------------------------

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name`          VARCHAR(120) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Catégories -------------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(120) NOT NULL,
  `slug`             VARCHAR(190) NOT NULL,
  `description`      VARCHAR(300) DEFAULT NULL,
  -- Ordre d'affichage dans les menus et le catalogue.
  `position`         INT NOT NULL DEFAULT 0,
  -- Référencement : surchargent les valeurs calculées si renseignés.
  `meta_title`       VARCHAR(70) DEFAULT NULL,
  `meta_description` VARCHAR(180) DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug` (`slug`),
  KEY `categories_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Produits ---------------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(160) NOT NULL,
  `slug`             VARCHAR(190) NOT NULL,
  `description`      TEXT NOT NULL,
  `category_id`      INT UNSIGNED NOT NULL,

  -- Prix de vente normal, en centimes.
  `price_cents`      INT UNSIGNED NOT NULL DEFAULT 0,

  -- Promotion. NULL = pas de promotion en cours.
  -- Elle n'est active que si un prix promo est renseigné ET que la date du
  -- jour tombe dans la fenêtre. Les deux bornes sont facultatives.
  `sale_price_cents` INT UNSIGNED DEFAULT NULL,
  `sale_starts_at`   DATE DEFAULT NULL,
  `sale_ends_at`     DATE DEFAULT NULL,

  -- 'brouillon' (invisible du public) ou 'en_ligne'.
  `status`           VARCHAR(20) NOT NULL DEFAULT 'brouillon',

  -- Stock utilisé quand le produit n'a PAS de déclinaison.
  -- Dès qu'il en existe, c'est leur stock qui fait foi.
  `stock`            INT NOT NULL DEFAULT 0,

  -- Mis en avant en haut de la page d'accueil.
  `featured`         TINYINT(1) NOT NULL DEFAULT 0,

  -- Vendu par un tiers : la fiche renvoie vers ce lien au lieu de proposer
  -- l'ajout au panier, et le produit ne peut pas être commandé sur le site.
  `external_url`     VARCHAR(500) DEFAULT NULL,
  `external_label`   VARCHAR(60) DEFAULT NULL,

  -- Également disponible à la boutique physique.
  `available_in_store` TINYINT(1) NOT NULL DEFAULT 0,

  -- Usages auxquels le produit répond : entraînement, compétition, loisir…
  -- Liste de valeurs séparées ET encadrées par des virgules
  -- (« ,entrainement,loisir, ») pour qu'un LIKE '%,loisir,%' ne puisse pas
  -- attraper un autre usage. Valeurs autorisées : src/Support/Usage.php.
  `usages`           VARCHAR(190) DEFAULT NULL,

  `meta_title`       VARCHAR(70) DEFAULT NULL,
  `meta_description` VARCHAR(180) DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug` (`slug`),
  KEY `products_category` (`category_id`),
  KEY `products_status` (`status`),
  KEY `products_featured` (`featured`),
  -- On empêche la suppression d'une catégorie qui contient encore des
  -- produits : ils se retrouveraient sans rayon.
  CONSTRAINT `products_category_fk` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Photos produits --------------------------------------------------------

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  -- Chemin public (/uploads/…) ou URL absolue si stockage externe.
  `url`        VARCHAR(500) NOT NULL,
  -- Texte alternatif, nécessaire à l'accessibilité et au référencement.
  `alt`        VARCHAR(200) NOT NULL,
  `position`   INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_images_product` (`product_id`, `position`),
  CONSTRAINT `product_images_product_fk` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Déclinaisons -----------------------------------------------------------

DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED NOT NULL,
  `size`        VARCHAR(40) DEFAULT NULL,
  `color`       VARCHAR(40) DEFAULT NULL,
  `sku`         VARCHAR(60) DEFAULT NULL,
  `stock`       INT NOT NULL DEFAULT 0,
  -- Prix spécifique à la déclinaison. NULL = prix du produit.
  `price_cents` INT UNSIGNED DEFAULT NULL,
  `position`    INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_variants_product` (`product_id`, `position`),
  CONSTRAINT `product_variants_product_fk` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Points de retrait ------------------------------------------------------

DROP TABLE IF EXISTS `pickup_points`;
CREATE TABLE `pickup_points` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `address_line1` VARCHAR(200) NOT NULL,
  `address_line2` VARCHAR(200) DEFAULT NULL,
  `postal_code`   VARCHAR(10) NOT NULL,
  `city`          VARCHAR(120) NOT NULL,
  -- Horaires en texte libre, affichés tels quels dans le tunnel.
  `hours`         VARCHAR(200) DEFAULT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `position`      INT NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Commandes --------------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Référence lisible communiquée au client, du type BG-7F3K2A.
  `reference`     VARCHAR(20) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `phone`         VARCHAR(30) DEFAULT NULL,

  -- 'en_attente', 'payee', 'en_preparation', 'expediee', 'retiree', 'annulee'
  `status`        VARCHAR(20) NOT NULL DEFAULT 'en_attente',
  -- 'livraison' ou 'retrait'
  `fulfilment`    VARCHAR(20) NOT NULL,

  -- Adresse, renseignée si fulfilment = 'livraison'
  `shipping_address_line1` VARCHAR(200) DEFAULT NULL,
  `shipping_address_line2` VARCHAR(200) DEFAULT NULL,
  `shipping_postal_code`   VARCHAR(10) DEFAULT NULL,
  `shipping_city`          VARCHAR(120) DEFAULT NULL,
  `shipping_country`       VARCHAR(2) DEFAULT NULL,

  -- Point de retrait, renseigné si fulfilment = 'retrait'
  `pickup_point_id` INT UNSIGNED DEFAULT NULL,

  `subtotal_cents` INT UNSIGNED NOT NULL,
  `shipping_cents` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_cents`    INT UNSIGNED NOT NULL,

  -- Traçabilité Stripe
  `stripe_session_id`        VARCHAR(190) DEFAULT NULL,
  `stripe_payment_intent_id` VARCHAR(190) DEFAULT NULL,
  `paid_at`                  DATETIME DEFAULT NULL,

  -- Note interne, visible uniquement dans l'administration.
  `admin_note`  TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_reference` (`reference`),
  UNIQUE KEY `orders_stripe_session` (`stripe_session_id`),
  KEY `orders_status` (`status`),
  KEY `orders_created` (`created_at`),
  CONSTRAINT `orders_pickup_point_fk` FOREIGN KEY (`pickup_point_id`)
    REFERENCES `pickup_points` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Lignes de commande -----------------------------------------------------
--
-- Les libellés et le prix sont RECOPIÉS au moment de la commande : si le
-- produit est renommé, son prix modifié ou le produit supprimé, la commande
-- passée garde ce qui a réellement été vendu.

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  -- Références d'origine, conservées pour les statistiques. Mises à NULL si
  -- le produit disparaît : la ligne reste lisible grâce aux libellés recopiés.
  `product_id` INT UNSIGNED DEFAULT NULL,
  `variant_id` INT UNSIGNED DEFAULT NULL,

  `product_name`  VARCHAR(160) NOT NULL,
  `variant_label` VARCHAR(90) DEFAULT NULL,
  `image_url`     VARCHAR(500) DEFAULT NULL,

  `unit_price_cents` INT UNSIGNED NOT NULL,
  `quantity`         INT UNSIGNED NOT NULL,
  `line_total_cents` INT UNSIGNED NOT NULL,

  PRIMARY KEY (`id`),
  KEY `order_items_order` (`order_id`),
  CONSTRAINT `order_items_order_fk` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_items_product_fk` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `order_items_variant_fk` FOREIGN KEY (`variant_id`)
    REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
