<?php

declare(strict_types=1);

/**
 * Rejoue le catalogue de démonstration figé dans `database/demo-arena.json`.
 *
 *   php database/importer-demo.php
 *   php database/importer-demo.php --purger
 *
 * Contrairement à `seed-demo.php`, ce script ne contacte personne : les
 * produits viennent du fichier versionné, les visuels du dossier
 * `public/uploads/demo/`. C'est ce qui permet à la démonstration en ligne de
 * se reconstruire en une seconde, à chaque réveil du conteneur, sans
 * solliciter le site d'arena.
 *
 * Les produits importés portent `demo_source = 'arena'` : l'administration
 * les marque d'une pastille « Démo », et `bin/apercu.php` refuse de publier
 * l'aperçu public tant qu'il en reste. Ils n'ont rien à faire dans une
 * boutique ouverte — ces photos ne nous appartiennent pas.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Support\Database;

$racine = dirname(__DIR__);
$purger = in_array('--purger', $argv, true);

// --- Purge -----------------------------------------------------------------
$anciens = Database::all("SELECT id FROM products WHERE demo_source = 'arena'");

if ($anciens !== []) {
    $ids = array_map(static fn (array $l): int => (int) $l['id'], $anciens);
    $trous = implode(',', array_fill(0, count($ids), '?'));

    // Les lignes de commande gardent leurs libellés recopiés : supprimer un
    // produit ne rend pas une commande illisible.
    Database::run("DELETE FROM product_images WHERE product_id IN ({$trous})", $ids);
    Database::run("DELETE FROM product_variants WHERE product_id IN ({$trous})", $ids);
    Database::run("DELETE FROM products WHERE id IN ({$trous})", $ids);

    printf("✓ %d produits de démonstration retirés\n", count($ids));
}

if ($purger) {
    exit(0);
}

// --- Lecture du fichier ----------------------------------------------------
$chemin = __DIR__ . '/demo-arena.json';

if (!is_file($chemin)) {
    exit("database/demo-arena.json est absent. Lancez database/exporter-demo.php depuis une base qui contient le catalogue.\n");
}

$donnees = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
$produits = $donnees['produits'] ?? [];

if ($produits === []) {
    exit("Le fichier ne contient aucun produit.\n");
}

// Les catégories sont désignées par leur identifiant d'URL, pas par un numéro :
// celui-ci change d'une installation à l'autre.
$categories = [];
foreach (Database::all('SELECT id, slug FROM categories') as $categorie) {
    $categories[$categorie['slug']] = (int) $categorie['id'];
}

// --- Import ----------------------------------------------------------------
$importes = 0;
$ignores = [];
$visuelsManquants = 0;

foreach ($produits as $produit) {
    $categorie = $categories[$produit['categorie']] ?? null;

    if ($categorie === null) {
        $ignores[] = $produit['name'] . ' (rayon « ' . $produit['categorie'] . ' » inconnu)';
        continue;
    }

    Database::run(
        'INSERT INTO products
            (name, slug, description, category_id, price_cents, sale_price_cents,
             status, stock, weight_grams, usages, available_in_store, featured,
             meta_title, meta_description, demo_source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $produit['name'],
            $produit['slug'],
            $produit['description'],
            $categorie,
            $produit['price_cents'],
            $produit['sale_price_cents'],
            \Bouge\Support\Status::PRODUCT_PUBLISHED,
            $produit['stock'],
            $produit['weight_grams'],
            $produit['usages'],
            $produit['available_in_store'],
            $produit['featured'],
            $produit['meta_title'],
            $produit['meta_description'],
            'arena',
        ]
    );

    $id = (int) Database::connection()->lastInsertId();

    foreach ($produit['images'] as $image) {
        // Un visuel absent n'arrête pas l'import : la fiche s'affichera sans
        // photo, ce qui vaut mieux qu'un catalogue à moitié chargé.
        if (!is_file($racine . '/public' . $image['url'])) {
            $visuelsManquants++;
            continue;
        }

        Database::run(
            'INSERT INTO product_images (product_id, url, alt, position) VALUES (?, ?, ?, ?)',
            [$id, $image['url'], $image['alt'], $image['position']]
        );
    }

    foreach ($produit['variants'] as $variante) {
        Database::run(
            'INSERT INTO product_variants (product_id, size, color, sku, stock, price_cents, position)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $variante['size'],
                $variante['color'],
                $variante['sku'],
                $variante['stock'],
                $variante['price_cents'],
                $variante['position'],
            ]
        );
    }

    $importes++;
}

printf("✓ %d produits de démonstration importés\n", $importes);

if ($visuelsManquants > 0) {
    printf("  %d visuel(s) introuvable(s) dans public/uploads/demo/\n", $visuelsManquants);
}

foreach ($ignores as $ignore) {
    echo "  ignoré : {$ignore}\n";
}
