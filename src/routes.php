<?php

declare(strict_types=1);

/**
 * Table des routes.
 *
 * @var \Bouge\Support\Router $router
 */

use Bouge\Controller\CatalogueController;
use Bouge\Controller\HomeController;
use Bouge\Controller\PageController;
use Bouge\Controller\ProductController;

// --- Boutique ----------------------------------------------------------------

$router->get('/', [HomeController::class, 'index']);
$router->get('/boutique', [CatalogueController::class, 'index']);
$router->get('/boutique/{slug}', [CatalogueController::class, 'category']);
$router->get('/produit/{slug}', [ProductController::class, 'show']);

// --- Pages éditoriales --------------------------------------------------------

$router->get('/mentions-legales', [PageController::class, 'legal']);
$router->get('/cgv', [PageController::class, 'terms']);
$router->get('/livraison', [PageController::class, 'shipping']);
