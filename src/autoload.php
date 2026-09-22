<?php

declare(strict_types=1);

/**
 * Chargement automatique des classes du projet.
 *
 * Volontairement écrit à la main plutôt que délégué à Composer : les classes
 * de la boutique se chargent ainsi même si `vendor/` venait à manquer sur le
 * serveur. Composer n'est utilisé que pour le SDK Stripe, chargé plus bas.
 *
 * Convention : la classe `Bouge\Repository\ProductRepository` vit dans
 * `src/Repository/ProductRepository.php`.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bouge\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// Fonctions globales des gabarits (e(), url(), asset()…).
require __DIR__ . '/Support/helpers.php';

// SDK Stripe, installé par Composer et livré dans `vendor/` pour qu'aucune
// installation ne soit nécessaire sur l'hébergement.
$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
}
