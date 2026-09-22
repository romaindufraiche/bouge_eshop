<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\ProductRepository;
use Bouge\Support\Session;
use Bouge\Support\View;

final class ProductController
{
    /** @param array<string, string> $params */
    public function show(array $params): string
    {
        $repository = new ProductRepository();
        $product = $repository->findPublishedBySlug($params['slug']);

        if ($product === null) {
            http_response_code(404);

            return View::render('boutique/404', ['title' => 'Produit introuvable', 'noindex' => true]);
        }

        // Photo affichée : index reçu en paramètre, ramené dans les bornes.
        $photoIndex = (int) ($_GET['photo'] ?? 0);
        $photoIndex = max(0, min($photoIndex, max(count($product['images']) - 1, 0)));

        return View::render('boutique/product', [
            'title'       => $product['meta_title'] ?: $product['name'],
            'description' => $product['meta_description'] ?: mb_substr($product['description'], 0, 160),
            'canonical'   => '/produit/' . $product['slug'],
            'product'     => $product,
            'related'     => $repository->related((int) $product['category_id'], (int) $product['id']),
            'photoIndex'  => $photoIndex,
            'error'       => Session::takeFlash('product_error'),
            'shop'        => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }
}
