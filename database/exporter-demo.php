<?php

declare(strict_types=1);

/**
 * Exporte le catalogue de démonstration vers un fichier versionné.
 *
 *   php database/exporter-demo.php
 *
 * `seed-demo.php` va chercher ses produits sur le site d'arena. C'est lent,
 * c'est fragile, et cela solliciterait leurs serveurs à chaque déploiement de
 * la démonstration. Ce script fige le résultat une bonne fois dans
 * `database/demo-arena.json`, que `importer-demo.php` rejoue hors ligne.
 *
 * À relancer seulement si le catalogue de démonstration doit changer.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Support\Database;

$produits = Database::all(
    "SELECT p.*, c.slug AS categorie
       FROM products p
       JOIN categories c ON c.id = p.category_id
      WHERE p.demo_source = 'arena'
      ORDER BY p.id"
);

if ($produits === []) {
    exit("Aucun produit de démonstration en base. Lancez d'abord database/seed-demo.php.\n");
}

$catalogue = [];

foreach ($produits as $produit) {
    $id = (int) $produit['id'];

    $images = array_map(
        static fn (array $image): array => [
            'url'      => $image['url'],
            'alt'      => $image['alt'],
            'position' => (int) $image['position'],
        ],
        Database::all(
            'SELECT url, alt, position FROM product_images WHERE product_id = ? ORDER BY position',
            [$id]
        )
    );

    $variantes = array_map(
        static fn (array $v): array => [
            'size'        => $v['size'],
            'color'       => $v['color'],
            'sku'         => $v['sku'],
            'stock'       => (int) $v['stock'],
            'price_cents' => $v['price_cents'] === null ? null : (int) $v['price_cents'],
            'position'    => (int) $v['position'],
        ],
        Database::all(
            'SELECT size, color, sku, stock, price_cents, position
               FROM product_variants WHERE product_id = ? ORDER BY position',
            [$id]
        )
    );

    $catalogue[] = [
        'categorie'        => $produit['categorie'],
        'name'             => $produit['name'],
        'slug'             => $produit['slug'],
        'description'      => $produit['description'],
        'price_cents'      => (int) $produit['price_cents'],
        'sale_price_cents' => $produit['sale_price_cents'] === null ? null : (int) $produit['sale_price_cents'],
        'stock'            => (int) $produit['stock'],
        'weight_grams'     => $produit['weight_grams'] === null ? null : (int) $produit['weight_grams'],
        'usages'           => $produit['usages'],
        'available_in_store' => (int) $produit['available_in_store'],
        'featured'         => (int) $produit['featured'],
        'meta_title'       => $produit['meta_title'],
        'meta_description' => $produit['meta_description'],
        'images'           => $images,
        'variants'         => $variantes,
    ];
}

$chemin = __DIR__ . '/demo-arena.json';

file_put_contents(
    $chemin,
    json_encode(
        [
            'source'  => 'arena',
            'exporte' => date('Y-m-d'),
            'produits' => $catalogue,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . "\n"
);

printf(
    "✓ %d produits écrits dans database/demo-arena.json (%s)\n",
    count($catalogue),
    number_format(filesize($chemin) / 1024, 0, ',', ' ') . ' Ko'
);
