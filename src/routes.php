<?php

declare(strict_types=1);

/**
 * Table des routes.
 *
 * @var \Bouge\Support\Router $router
 */

use Bouge\Controller\CartController;
use Bouge\Controller\CatalogueController;
use Bouge\Controller\CheckoutController;
use Bouge\Controller\HomeController;
use Bouge\Controller\PageController;
use Bouge\Controller\ProductController;
use Bouge\Controller\WebhookController;

// --- Boutique ----------------------------------------------------------------

$router->get('/', [HomeController::class, 'index']);
$router->get('/boutique', [CatalogueController::class, 'index']);
$router->get('/boutique/{slug}', [CatalogueController::class, 'category']);
$router->get('/produit/{slug}', [ProductController::class, 'show']);

// --- Pages éditoriales --------------------------------------------------------

$router->get('/mentions-legales', [PageController::class, 'legal']);
$router->get('/cgv', [PageController::class, 'terms']);
$router->get('/livraison', [PageController::class, 'shipping']);

// --- Panier --------------------------------------------------------------------

$router->get('/panier', [CartController::class, 'show']);
$router->post('/panier/ajouter', [CartController::class, 'add']);
$router->post('/panier/modifier', [CartController::class, 'update']);
$router->post('/panier/retirer', [CartController::class, 'remove']);

// --- Commande ------------------------------------------------------------------

$router->get('/commande', [CheckoutController::class, 'form']);
$router->post('/commande', [CheckoutController::class, 'submit']);
$router->get('/commande/confirmation', [CheckoutController::class, 'confirmation']);

// --- Webhook Stripe --------------------------------------------------------------
// Seul endroit où une commande devient « payée » et où le stock est décompté.

$router->post('/webhook/stripe', [WebhookController::class, 'stripe']);
