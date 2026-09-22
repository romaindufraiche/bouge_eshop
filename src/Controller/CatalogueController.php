<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CategoryRepository;
use Bouge\Repository\ProductRepository;
use Bouge\Support\View;

final class CatalogueController
{
    /** Tout le matériel. */
    public function index(): string
    {
        $products = (new ProductRepository())->published();

        return View::render('boutique/catalogue', [
            'title'       => 'Tout le matériel de natation',
            'description' => "L'ensemble du catalogue BOUGE. : bonnets, lunettes, accessoires "
                . 'et vêtements de natation. Livraison en France ou retrait sur place.',
            'canonical'   => '/boutique',
            'categories'  => (new CategoryRepository())->all(),
            'products'    => $products,
            'category'    => null,
        ]);
    }

    /** @param array<string, string> $params */
    public function category(array $params): string
    {
        $categories = new CategoryRepository();
        $category = $categories->findBySlug($params['slug']);

        if ($category === null) {
            http_response_code(404);

            return View::render('boutique/404', ['title' => 'Catégorie introuvable', 'noindex' => true]);
        }

        $products = (new ProductRepository())->published((int) $category['id']);

        return View::render('boutique/catalogue', [
            'title'       => $category['meta_title'] ?: $category['name'] . ' de natation',
            'description' => $category['meta_description']
                ?: ($category['description'] ?: 'Tous nos produits de la catégorie ' . $category['name'] . '.'),
            'canonical'   => '/boutique/' . $category['slug'],
            'categories'  => $categories->all(),
            'products'    => $products,
            'category'    => $category,
        ]);
    }
}
