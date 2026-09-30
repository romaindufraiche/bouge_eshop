-- ---------------------------------------------------------------------------
-- Réinitialisation du mot de passe client
--
--   mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/008-mot-de-passe-oublie.sql
--
-- Le jeton n'est pas stocké : seule son empreinte l'est. Quelqu'un qui
-- obtiendrait une copie de la base ne pourrait pas s'en servir pour prendre la
-- main sur un compte — c'est la même logique que pour les mots de passe.
--
-- Inutile sur une nouvelle installation : `database/schema.sql` contient déjà
-- la table.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  -- Empreinte SHA-256 du jeton envoyé par courriel, jamais le jeton lui-même.
  `token_hash`  CHAR(64) NOT NULL,
  `expires_at`  DATETIME NOT NULL,
  -- Renseignée à la première utilisation : un lien ne sert qu'une fois.
  `used_at`     DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_token` (`token_hash`),
  KEY `password_resets_customer` (`customer_id`),
  CONSTRAINT `password_resets_customer_fk` FOREIGN KEY (`customer_id`)
    REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
