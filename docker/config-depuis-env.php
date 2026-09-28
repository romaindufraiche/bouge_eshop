<?php

declare(strict_types=1);

/**
 * Fabrique config/config.php à partir des variables d'environnement.
 *
 * Sur un hébergement mutualisé, ce fichier est écrit à la main une fois pour
 * toutes. Dans un conteneur il n'existe pas : le système de fichiers est
 * remplacé à chaque déploiement, et y déposer des mots de passe reviendrait à
 * les mettre dans l'image. Ils viennent donc de l'environnement, que Render
 * chiffre et n'affiche jamais dans les journaux.
 *
 * Aucune valeur n'est inventée : ce qui manque reste vide, et la boutique
 * réagit comme elle le fait partout ailleurs quand une clé manque — Stripe se
 * désactive, le transporteur aussi.
 */

$lire = static function (string $cle, string $defaut = ''): string {
    $valeur = getenv($cle);

    return $valeur === false || $valeur === '' ? $defaut : $valeur;
};

// Render publie l'adresse du service dans RENDER_EXTERNAL_URL : la reprendre
// évite d'avoir à la recopier après le premier déploiement, quand on ne la
// connaît pas encore.
$adresse = rtrim($lire('BOUGE_SITE_URL', $lire('RENDER_EXTERNAL_URL')), '/');

$config = [
    'db' => [
        'host'     => $lire('BOUGE_DB_HOST', '127.0.0.1'),
        'name'     => $lire('BOUGE_DB_NAME', 'bouge'),
        // Les mêmes valeurs par défaut que l'entrypoint, qui crée
        // l'utilisateur : les deux doivent s'accorder, sinon la boutique ne se
        // connecte pas à sa propre base.
        'user'     => $lire('BOUGE_DB_USER', 'bouge'),
        'password' => $lire('BOUGE_DB_PASSWORD', 'bouge'),
        'port'     => $lire('BOUGE_DB_PORT'),
        'socket'   => $lire('BOUGE_DB_SOCKET'),
    ],

    'site_url' => $adresse,

    // Jamais true sur une adresse publique : le mode debug affiche les
    // requêtes et les chemins du serveur.
    'debug' => false,

    'stripe' => [
        'secret_key'      => $lire('STRIPE_SECRET_KEY'),
        'publishable_key' => $lire('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret'  => $lire('STRIPE_WEBHOOK_SECRET'),
    ],

    'boxtal' => [
        'user'     => $lire('BOXTAL_USER'),
        'password' => $lire('BOXTAL_PASSWORD'),
        'test'     => $lire('BOXTAL_TEST', '1') !== '0',
    ],
];

$chemin = dirname(__DIR__) . '/config/config.php';

file_put_contents(
    $chemin,
    "<?php\n\n"
    . "// Fichier fabriqué au démarrage du conteneur par docker/config-depuis-env.php.\n"
    . "// Toute modification faite ici sera perdue au prochain déploiement.\n\n"
    . 'return ' . var_export($config, true) . ";\n"
);

// Lisible par son propriétaire seulement : il porte les clés de paiement.
// L'entrypoint le donne ensuite à l'utilisateur d'Apache, qui doit le lire.
chmod($chemin, 0o600);
