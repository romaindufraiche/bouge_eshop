<?php

declare(strict_types=1);

/**
 * Retire du catalogue les produits de démonstration.
 *
 * Les cinquante-quatre articles arena et leurs photos sont là pour montrer à
 * quoi ressemble la boutique pleine. Ils appartiennent à arena : ils n'ont
 * rien à faire dans une boutique qui ouvre, et `bin/apercu.php` refuse
 * d'ailleurs de publier l'aperçu public tant qu'il en reste.
 *
 *     php database/purger-demo.php            # dit ce qui partirait
 *     php database/purger-demo.php --vraiment # le fait
 *
 * Les commandes déjà passées ne bougent pas : leurs lignes ont recopié le
 * nom, le prix et l'image à l'achat, et l'historique des ventes reste donc
 * lisible après la disparition des produits.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Support\Database;
use Bouge\Support\Uploads;

$vraiment = in_array('--vraiment', $argv, true);

$produits = Database::all(
    "SELECT id, name, demo_source FROM products WHERE demo_source IS NOT NULL AND demo_source <> ''"
);

if ($produits === []) {
    echo "Aucun produit de démonstration : le catalogue est déjà propre.\n";
    exit(0);
}

$parSource = [];

foreach ($produits as $produit) {
    $parSource[(string) $produit['demo_source']][] = $produit;
}

foreach ($parSource as $source => $liste) {
    printf("%-10s %d produits\n", $source, count($liste));
}

$ids = array_map(static fn (array $p): int => (int) $p['id'], $produits);
$trous = implode(', ', array_fill(0, count($ids), '?'));

$photos = Database::all("SELECT url FROM product_images WHERE product_id IN ({$trous})", $ids);
printf("%-10s %d photos\n", '', count($photos));

if (!$vraiment) {
    echo "\nRien n'a été supprimé. Relancez avec --vraiment pour le faire.\n";
    exit(0);
}

// Les fichiers d'abord : en cas d'incident, mieux vaut une image orpheline
// sur le disque qu'une fiche qui pointe vers du vide.
$retires = 0;

foreach ($photos as $photo) {
    $url = (string) $photo['url'];

    // Les visuels de démonstration sont versionnés sous /uploads/demo/ :
    // les effacer du disque supprimerait des fichiers suivis par Git.
    if (str_starts_with($url, '/uploads/demo/')) {
        continue;
    }

    Uploads::delete($url);
    $retires++;
}

$supprimes = Database::run("DELETE FROM products WHERE id IN ({$trous})", $ids)->rowCount();

printf(
    "\n✓ %d produits supprimés, %d fichiers retirés du disque.\n",
    $supprimes,
    $retires
);
echo "  Les visuels versionnés sous /uploads/demo/ restent dans le dépôt ;\n";
echo "  ils ne sont plus rattachés à aucun produit.\n";
