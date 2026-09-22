<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CategoryRepository;
use Bouge\Repository\ProductRepository;
use Bouge\Support\Config;
use Bouge\Support\View;

final class HomeController
{
    public function index(): string
    {
        $products = new ProductRepository();
        $highlighted = $products->highlighted();

        $selection = $products->forHomepage(8);

        // Le produit mis en avant n'a pas à réapparaître dans « À découvrir ».
        if ($highlighted !== null) {
            $selection = array_values(array_filter(
                $selection,
                static fn (array $p): bool => (int) $p['id'] !== (int) $highlighted['id']
            ));
        }

        return View::render('boutique/home', [
            'title'       => null,
            'description' => 'Bonnets, lunettes, accessoires et vêtements de natation. '
                . 'Livraison en France ou retrait sur place.',
            'canonical'   => '/',
            'categories'  => (new CategoryRepository())->all(),
            'products'    => $selection,
            'highlighted' => $highlighted,
            'shop'        => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }
}
