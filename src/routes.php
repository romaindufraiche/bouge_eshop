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
use Bouge\Controller\Admin\AuthController as AdminAuthController;
use Bouge\Controller\Admin\CategoryController as AdminCategoryController;
use Bouge\Controller\Admin\DashboardController;
use Bouge\Controller\Admin\OrderController as AdminOrderController;
use Bouge\Controller\Admin\PickupPointController as AdminPickupPointController;
use Bouge\Controller\Admin\ProductController as AdminProductController;

// --- Boutique ----------------------------------------------------------------

$router->get('/', [HomeController::class, 'index']);
$router->get('/boutique', [CatalogueController::class, 'index']);
$router->get('/recherche', [CatalogueController::class, 'search']);
// Les chemins fixes passent avant `{slug}`, sans quoi « selection » serait
// pris pour une catégorie.
$router->get('/boutique/selection/{slug}', [CatalogueController::class, 'shortcut']);
$router->get('/boutique/{slug}', [CatalogueController::class, 'category']);
$router->get('/usage/{slug}', [CatalogueController::class, 'usage']);
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

// --- Administration ---------------------------------------------------------
// Toutes ces pages appellent Auth::require() en première ligne : aucune n'est
// accessible sans session ouverte.

$router->get('/admin/connexion', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/connexion', [AdminAuthController::class, 'login']);
$router->post('/admin/deconnexion', [AdminAuthController::class, 'logout']);

$router->get('/admin', [DashboardController::class, 'index']);

// Les chemins fixes sont déclarés avant `{id}` : sans cela, « nouveau »
// serait pris pour un identifiant.
$router->get('/admin/produits', [AdminProductController::class, 'index']);
$router->get('/admin/produits/nouveau', [AdminProductController::class, 'create']);
$router->get('/admin/produits/{id}', [AdminProductController::class, 'edit']);
$router->post('/admin/produits/enregistrer', [AdminProductController::class, 'save']);
$router->post('/admin/produits/supprimer', [AdminProductController::class, 'delete']);
$router->post('/admin/produits/photos', [AdminProductController::class, 'uploadImages']);
$router->post('/admin/produits/photo/supprimer', [AdminProductController::class, 'deleteImage']);
$router->post('/admin/produits/photo/deplacer', [AdminProductController::class, 'moveImage']);
$router->post('/admin/produits/photo/texte', [AdminProductController::class, 'updateImageAlt']);

$router->get('/admin/categories', [AdminCategoryController::class, 'index']);
$router->post('/admin/categories/enregistrer', [AdminCategoryController::class, 'save']);
$router->post('/admin/categories/supprimer', [AdminCategoryController::class, 'delete']);

$router->get('/admin/points-de-retrait', [AdminPickupPointController::class, 'index']);
$router->post('/admin/points-de-retrait/enregistrer', [AdminPickupPointController::class, 'save']);
$router->post('/admin/points-de-retrait/supprimer', [AdminPickupPointController::class, 'delete']);

$router->get('/admin/commandes', [AdminOrderController::class, 'index']);
$router->get('/admin/commandes/{id}', [AdminOrderController::class, 'show']);
$router->post('/admin/commandes/statut', [AdminOrderController::class, 'updateStatus']);
$router->post('/admin/commandes/note', [AdminOrderController::class, 'updateNote']);
