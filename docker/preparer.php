<?php

declare(strict_types=1);

/**
 * Prépare la base de démonstration au démarrage du conteneur.
 *
 * Écrit pour être relancé sans dommage : il regarde ce qui existe avant
 * d'agir. Le conteneur repart d'une image neuve à chaque réveil, mais si un
 * jour un disque persistant est ajouté, ce script ne réinstallera rien.
 *
 * Deux gestes, dans l'ordre :
 *   1. poser le schéma et le catalogue de démonstration si les tables sont
 *      absentes ;
 *   2. créer ou mettre à jour le compte d'administration d'après
 *      l'environnement — il n'est jamais écrit dans l'image.
 *
 * La base et son utilisateur sont créés avant, par l'entrypoint : cela demande
 * les droits d'administration de MariaDB, que la boutique n'a pas et ne doit
 * pas avoir.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Repository\AdminUserRepository;
use Bouge\Support\Database;

$lire = static function (string $cle, string $defaut = ''): string {
    $valeur = getenv($cle);

    return $valeur === false || $valeur === '' ? $defaut : $valeur;
};

$racine = dirname(__DIR__);

// --- 1. Le schéma et le catalogue -----------------------------------------
$tables = (int) Database::connection()
    ->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
    ->fetchColumn();

if ($tables === 0) {
    // Le schéma est joué requête par requête : PDO::exec() n'accepte pas
    // toujours un fichier entier selon le pilote.
    $sql = (string) file_get_contents($racine . '/database/schema.sql');
    Database::connection()->exec($sql);

    echo "✓ Schéma posé\n";

    // Le catalogue de base : cinq rayons, quatorze produits, leurs visuels
    // provisoires.
    passthru(sprintf('%s %s/database/seed.php', escapeshellarg(PHP_BINARY), escapeshellarg($racine)), $code);

    if ($code !== 0) {
        fwrite(STDERR, "Le catalogue de démonstration n'a pas pu être chargé.\n");
        exit(1);
    }

    // Puis le catalogue arena, rejoué depuis le fichier versionné — jamais
    // rescrapé : leurs serveurs n'ont pas à être sollicités à chaque réveil du
    // conteneur. Ces produits portent `demo_source = 'arena'`, ce qui les
    // signale dans l'administration et empêche la publication de l'aperçu
    // public tant qu'ils sont là.
    passthru(sprintf('%s %s/database/importer-demo.php', escapeshellarg(PHP_BINARY), escapeshellarg($racine)), $code);

    if ($code !== 0) {
        // Une démonstration sans les visuels arena vaut mieux qu'une
        // démonstration qui ne démarre pas.
        fwrite(STDERR, "Le catalogue arena n'a pas pu être importé ; la boutique démarre sans lui.\n");
    }
} else {
    echo "✓ Base déjà installée, rien à refaire\n";
}

// --- 2. Le compte d'administration ----------------------------------------
$email = $lire('BOUGE_ADMIN_EMAIL');
$motDePasse = $lire('BOUGE_ADMIN_PASSWORD');

if ($email === '' || $motDePasse === '') {
    fwrite(STDERR, "BOUGE_ADMIN_EMAIL et BOUGE_ADMIN_PASSWORD sont nécessaires : sans eux, personne ne peut entrer dans l'administration.\n");
    exit(1);
}

if (mb_strlen($motDePasse) < 12) {
    fwrite(STDERR, "BOUGE_ADMIN_PASSWORD doit faire au moins 12 caractères.\n");
    exit(1);
}

(new AdminUserRepository())->upsert($email, $motDePasse, 'Administration BOUGE Club');

// `seed.php` crée au passage un compte de développement dont le mot de passe
// est écrit en clair dans le dépôt. Sur une adresse publique, ce serait une
// porte ouverte : il ne doit rester qu'un seul compte, celui de
// l'environnement.
$supprimes = Database::run(
    'DELETE FROM admin_users WHERE email <> ?',
    [$email]
)->rowCount();

echo "✓ Compte d'administration : {$email}\n";

if ($supprimes > 0) {
    echo "✓ {$supprimes} compte(s) de développement supprimé(s)\n";
}
