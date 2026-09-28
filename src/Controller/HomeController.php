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

        // La vitrine qui défile en haut de page. Elle n'a de sens qu'avec des
        // photos : un produit sans visuel y ferait un trou.
        $vitrine = array_values(array_filter(
            $products->browse(limite: 60),
            static fn (array $p): bool => ($p['cover'] ?? null) !== null
        ));

        // Sans mélange, la vitrine montre les douze produits les plus récents
        // — qui sont souvent du même rayon, et donnent l'impression que la
        // boutique ne vend qu'une chose. Le tirage change aussi à chaque
        // visite, ce qui donne au catalogue l'air plus vaste qu'une bande
        // figée.
        shuffle($vitrine);

        return View::render('boutique/home', [
            'title'       => null,
            'description' => 'Bonnets, lunettes, accessoires et maillots de natation. '
                . 'Livraison en France ou retrait sur place.',
            'canonical'   => '/',
            'categories'  => (new CategoryRepository())->all(),
            'products'    => $selection,
            'vitrine'     => array_slice($vitrine, 0, 12),
            'highlighted' => $highlighted,
            'shop'        => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }
}
