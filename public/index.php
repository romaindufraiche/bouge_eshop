<?php

declare(strict_types=1);

/**
 * Contrôleur frontal.
 *
 * Toutes les requêtes passent par ce fichier, redirigées par le .htaccess.
 * Aucun autre fichier PHP n'est accessible directement : le code de la
 * boutique vit en dehors du dossier public.
 */

use Bouge\Support\Config;
use Bouge\Support\Router;
use Bouge\Support\Session;
use Bouge\Support\View;

require dirname(__DIR__) . '/src/autoload.php';

// --- Affichage des erreurs ---------------------------------------------------
// En production, une erreur détaillée affichée au public révèle la structure
// du site et parfois des identifiants : on la journalise sans la montrer.
$debug = Config::get('debug') === true;
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// --- En-têtes de sécurité ----------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

Session::start();

// Données disponibles dans tous les gabarits.
View::share('shop', require dirname(__DIR__) . '/config/shop.php');

$router = new Router();
require dirname(__DIR__) . '/src/routes.php';

try {
    $found = $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/'
    );

    if (!$found) {
        http_response_code(404);
        echo View::render('boutique/404', ['title' => 'Page introuvable']);
    }
} catch (Throwable $e) {
    error_log((string) $e);

    if ($debug) {
        throw $e;
    }

    http_response_code(500);
    echo View::render('boutique/500', ['title' => 'Erreur']);
}
