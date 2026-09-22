<?php

declare(strict_types=1);

/**
 * Installation de la boutique.
 *
 *   php database/install.php contact@votre-domaine.fr "mot de passe"
 *
 * Crée les tables à partir de `database/schema.sql`, puis le compte
 * d'administration. Rien d'autre : le catalogue reste vide, prêt à être
 * rempli depuis /admin.
 *
 * Pour un catalogue de démonstration, utilisez `database/seed.php` à la
 * place — mais jamais sur une boutique déjà ouverte : il vide le catalogue.
 *
 * ATTENTION : le script recrée les tables et efface donc TOUT le contenu
 * existant, commandes comprises. Il est fait pour une première installation.
 */

use Bouge\Repository\AdminUserRepository;
use Bouge\Support\Database;

require dirname(__DIR__) . '/src/autoload.php';

// --- Vérifications préalables --------------------------------------------------
// Mieux vaut un message clair ici qu'une erreur incompréhensible plus tard.

if (PHP_VERSION_ID < 80100) {
    exit("PHP 8.1 ou plus récent est nécessaire (vous avez " . PHP_VERSION . ").\n");
}

foreach (['pdo_mysql', 'mbstring', 'curl'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("L'extension PHP « {$extension} » est absente. Demandez son activation à votre hébergeur.\n");
    }
}

if (!is_file(dirname(__DIR__) . '/config/config.php')) {
    exit("Copiez d'abord config/config.example.php en config/config.php et renseignez vos identifiants.\n");
}

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null || $password === null) {
    exit("Usage : php database/install.php <adresse@exemple.fr> <mot-de-passe>\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("« {$email} » n'est pas une adresse électronique valide.\n");
}

// 12 caractères : un mot de passe court tient quelques heures face à un
// outil d'attaque automatisé, et ce compte ouvre toute la boutique.
if (mb_strlen($password) < 12) {
    exit("Choisissez un mot de passe d'au moins 12 caractères.\n");
}

// --- Connexion ------------------------------------------------------------------

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    exit("Connexion à la base impossible. Vérifiez la section « db » de config/config.php.\n"
        . 'Détail : ' . $e->getMessage() . "\n");
}

echo "✓ Connexion à la base établie\n";

// --- Tables -----------------------------------------------------------------------
// Le fichier est joué d'un bloc : les instructions y sont ordonnées pour que
// les clés étrangères trouvent toujours leur table de destination.

$sql = file_get_contents(__DIR__ . '/schema.sql');

if ($sql === false) {
    exit("Le fichier database/schema.sql est introuvable.\n");
}

$pdo->exec($sql);

echo "✓ Tables créées\n";

// --- Compte d'administration ---------------------------------------------------

(new AdminUserRepository())->upsert($email, $password, 'Administration BOUGE.');

echo "✓ Compte d'administration : {$email}\n\n";
echo "Installation terminée. Connectez-vous sur <votre-site>/admin/connexion,\n";
echo "puis créez vos catégories avant d'ajouter vos premiers produits.\n";
