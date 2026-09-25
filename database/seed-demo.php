<?php

declare(strict_types=1);

/**
 * Catalogue de démonstration, importé depuis un site de la profession.
 *
 *   php database/seed-demo.php            importe (après avoir purgé l'ancien)
 *   php database/seed-demo.php --purger   retire les produits de démonstration
 *
 * À quoi ça sert : montrer la boutique avec de vrais visuels et de vrais
 * intitulés avant que le catalogue du client n'existe. Des formes abstraites
 * ne donnent pas la même idée du rendu.
 *
 * Ce que ce n'est pas : un catalogue de vente. Les visuels et les intitulés
 * appartiennent à arena, leur propriétaire. Ils sont donc :
 *   - marqués en base (colonne `demo_source`) et signalés dans l'administration ;
 *   - téléchargés dans public/uploads/demo/, dossier que Git ignore — ils ne
 *     partent donc ni dans le dépôt, ni dans l'aperçu public ;
 *   - retirables d'une commande, le jour où les vrais produits arrivent.
 *
 * Le jour de l'ouverture, ils n'ont plus rien à faire là.
 */

use Bouge\Support\Database;
use Bouge\Support\Slug;
use Bouge\Support\Status;
use Bouge\Support\Usage;

require dirname(__DIR__) . '/src/autoload.php';

const SOURCE = 'arena';
const DOSSIER_IMAGES = '/uploads/demo/';

/** Pages à importer, et la catégorie de la boutique où les ranger. */
$sources = [
    [
        'categorie' => 'lunettes',
        'url'       => 'https://www.arenasport.com/fr_fr/equipement/lunettes-de-natation.html',
        'usages'    => [Usage::TRAINING, Usage::COMPETITION],
        'limite'    => 12,
    ],
    [
        'categorie' => 'bonnets',
        'url'       => 'https://www.arenasport.com/fr_fr/equipement/bonnets-de-natation.html',
        'usages'    => [Usage::TRAINING, Usage::LEISURE],
        'limite'    => 10,
    ],
    [
        'categorie' => 'accessoires',
        'url'       => 'https://www.arenasport.com/fr_fr/equipement/accessoires-de-natation.html',
        'usages'    => [Usage::TRAINING],
        'limite'    => 10,
    ],
    [
        'categorie' => 'accessoires',
        'url'       => 'https://www.arenasport.com/fr_fr/equipement/sacs-et-sacs-a-dos.html',
        'usages'    => [Usage::TRAINING, Usage::LEISURE],
        'limite'    => 6,
    ],
    [
        'categorie' => 'vetements',
        'url'       => 'https://www.arenasport.com/fr_fr/homme/maillots-de-bain/jammers.html',
        'usages'    => [Usage::TRAINING, Usage::COMPETITION],
        'limite'    => 8,
    ],
    [
        'categorie' => 'vetements',
        'url'       => 'https://www.arenasport.com/fr_fr/femme/maillots-de-bain/maillots-une-piece.html',
        'usages'    => [Usage::TRAINING, Usage::LEISURE],
        'limite'    => 8,
    ],
];

// --- Purge ---------------------------------------------------------------------

/** Retire les produits de démonstration et leurs visuels. */
function purger(): int
{
    $produits = Database::all(
        'SELECT id FROM products WHERE demo_source IS NOT NULL'
    );

    foreach ($produits as $produit) {
        $images = Database::all(
            'SELECT url FROM product_images WHERE product_id = ?',
            [(int) $produit['id']]
        );

        foreach ($images as $image) {
            $chemin = dirname(__DIR__) . '/public' . $image['url'];
            // basename() : on ne supprime jamais hors du dossier de démo.
            if (str_starts_with((string) $image['url'], DOSSIER_IMAGES) && is_file($chemin)) {
                unlink($chemin);
            }
        }

        Database::run('DELETE FROM products WHERE id = ?', [(int) $produit['id']]);
    }

    return count($produits);
}

if (in_array('--purger', $argv, true)) {
    $retires = purger();
    echo "✓ {$retires} produits de démonstration retirés\n";
    exit;
}

// --- Récupération ----------------------------------------------------------------

/** Télécharge une page, avec un en-tête de navigateur ordinaire. */
function recuperer(string $url): ?string
{
    $contexte = stream_context_create([
        'http' => [
            'timeout' => 45,
            'header'  => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                . "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36\r\n"
                . "Accept-Language: fr-FR,fr;q=0.9\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $html = @file_get_contents($url, false, $contexte);

    return $html === false ? null : $html;
}

/**
 * Extrait les produits d'une page de catégorie.
 *
 * La page est découpée par vignette : chacune porte son lien, son nom, son
 * prix, ses couleurs, ses tailles et son visuel. La lecture est volontairement
 * tolérante — une vignette incomplète est ignorée plutôt que de faire échouer
 * tout l'import.
 *
 * @return array<int, array<string, mixed>>
 */
function extraire(string $html, int $limite): array
{
    $morceaux = preg_split('/(?=<a\s+class="product-item-link)/', $html) ?: [];
    $produits = [];
    $vus = [];

    foreach (array_slice($morceaux, 1) as $morceau) {
        if (count($produits) >= $limite) {
            break;
        }

        if (
            preg_match('#href="(https://www\.arenasport\.com/fr_fr/[^"]+)"#', $morceau, $lien) !== 1
            || preg_match('#>\s*([^<]{6,140}?)\s*</a>#', $morceau, $nom) !== 1
            || preg_match('#"finalPrice":\{"amount":([\d.]+)\}#', $morceau, $prix) !== 1
            || preg_match('#(https://www\.arenasport\.com/media/catalog/product/cache/[^"\']+\.(?:jpg|png|webp))#', $morceau, $image) !== 1
        ) {
            continue;
        }

        $libelle = trim(html_entity_decode($nom[1], ENT_QUOTES, 'UTF-8'));

        if ($libelle === '' || isset($vus[$libelle])) {
            continue;
        }
        $vus[$libelle] = true;

        preg_match('#"oldPrice":\{"amount":([\d.]+)\}#', $morceau, $ancien);
        $produits[] = [
            'nom'      => $libelle,
            'source'   => $lien[1],
            'prix'     => (int) round(((float) $prix[1]) * 100),
            'ancien'   => isset($ancien[1]) ? (int) round(((float) $ancien[1]) * 100) : null,
            'image'    => $image[1],
            'couleurs' => etiquettes($morceau, 'color'),
            'tailles'  => etiquettes($morceau, 'size'),
        ];
    }

    return $produits;
}

/** @return array<int, string> */
function etiquettes(string $morceau, string $code): array
{
    if (preg_match('#"code":"' . $code . '".*?"options":\[(.*?)\]#s', $morceau, $bloc) !== 1) {
        return [];
    }

    preg_match_all('#"label":"([^"]+)"#', $bloc[1], $trouves);

    $etiquettes = array_values(array_unique(array_map(
        static fn (string $e): string => mb_convert_case(
            trim(html_entity_decode($e, ENT_QUOTES, 'UTF-8')),
            MB_CASE_TITLE,
            'UTF-8'
        ),
        $trouves[1]
    )));

    return array_slice($etiquettes, 0, 6);
}

/** Enregistre le visuel et renvoie son adresse publique, ou null. */
function telecharger(string $url, string $slug): ?string
{
    $dossier = dirname(__DIR__) . '/public' . DOSSIER_IMAGES;

    if (!is_dir($dossier) && !mkdir($dossier, 0755, true) && !is_dir($dossier)) {
        return null;
    }

    $contenu = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 30, 'header' => "User-Agent: Mozilla/5.0\r\n"],
    ]));

    if ($contenu === false || strlen($contenu) < 1024) {
        return null;
    }

    // L'extension vient du contenu, pas de l'adresse : un fichier annoncé
    // .jpg qui n'en est pas ne doit pas être enregistré comme tel.
    $type = (new finfo(FILEINFO_MIME_TYPE))->buffer($contenu);
    $extensions = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];

    if (!isset($extensions[$type])) {
        return null;
    }

    $fichier = $slug . $extensions[$type];
    file_put_contents($dossier . $fichier, $contenu);

    return DOSSIER_IMAGES . $fichier;
}

// --- Import ------------------------------------------------------------------------

$categories = [];
foreach (Database::all('SELECT id, slug FROM categories') as $categorie) {
    $categories[$categorie['slug']] = (int) $categorie['id'];
}

if ($categories === []) {
    exit("Aucune catégorie en base. Lancez d'abord : php database/seed.php\n");
}

$retires = purger();
if ($retires > 0) {
    echo "✓ {$retires} produits de démonstration précédents retirés\n";
}

$total = 0;
$sansVisuel = 0;

foreach ($sources as $source) {
    $categorieId = $categories[$source['categorie']] ?? null;

    if ($categorieId === null) {
        echo "⚠ Catégorie « {$source['categorie']} » absente, page ignorée\n";
        continue;
    }

    echo "→ {$source['url']}\n";
    $html = recuperer($source['url']);

    if ($html === null) {
        echo "⚠ Page injoignable, on passe à la suivante\n";
        continue;
    }

    $produits = extraire($html, $source['limite']);
    echo "  " . count($produits) . " produits lus\n";

    foreach ($produits as $produit) {
        $slug = Slug::unique(
            $produit['nom'],
            static fn (string $candidat): bool =>
                Database::first('SELECT id FROM products WHERE slug = ?', [$candidat]) !== null
        );

        // Une remise n'est retenue que si elle est réelle.
        $promo = $produit['ancien'] !== null && $produit['ancien'] > $produit['prix']
            ? $produit['prix']
            : null;
        $prix = $promo === null ? $produit['prix'] : $produit['ancien'];

        Database::run(
            'INSERT INTO products
               (name, slug, description, category_id, price_cents, sale_price_cents,
                status, stock, usages, demo_source, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $produit['nom'],
                $slug,
                description($produit),
                $categorieId,
                $prix,
                $promo,
                Status::PRODUCT_PUBLISHED,
                random_int(6, 40),
                Usage::toStorage($source['usages']),
                SOURCE,
                mb_substr($produit['nom'], 0, 180),
            ]
        );

        $produitId = (int) Database::connection()->lastInsertId();
        $url = telecharger($produit['image'], $slug);

        if ($url === null) {
            $sansVisuel++;
        } else {
            Database::run(
                'INSERT INTO product_images (product_id, url, alt, position) VALUES (?, ?, ?, 0)',
                [$produitId, $url, $produit['nom']]
            );
        }

        // Déclinaisons : les tailles si le produit en a, sinon les couleurs.
        $declinaisons = $produit['tailles'] !== [] ? $produit['tailles'] : $produit['couleurs'];
        $taillesPlutotQueCouleurs = $produit['tailles'] !== [];

        foreach ($declinaisons as $position => $etiquette) {
            if (mb_strtolower($etiquette) === 'taille Unique' || $etiquette === '') {
                continue;
            }

            Database::run(
                'INSERT INTO product_variants (product_id, size, color, stock, position)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $produitId,
                    $taillesPlutotQueCouleurs ? $etiquette : null,
                    $taillesPlutotQueCouleurs ? null : $etiquette,
                    random_int(0, 14),
                    $position,
                ]
            );
        }

        $total++;
    }
}

/** @param array<string, mixed> $produit */
function description(array $produit): string
{
    $lignes = ["Produit de démonstration, en attendant le catalogue de la boutique."];

    if ($produit['couleurs'] !== []) {
        $lignes[] = 'Coloris : ' . implode(', ', $produit['couleurs']) . '.';
    }

    if ($produit['tailles'] !== []) {
        $lignes[] = 'Tailles : ' . implode(', ', $produit['tailles']) . '.';
    }

    return implode("\n", $lignes);
}

echo "\n✓ {$total} produits de démonstration importés\n";

if ($sansVisuel > 0) {
    echo "⚠ {$sansVisuel} sans visuel : ils s'afficheront avec « Photo à venir »\n";
}

echo "\nCes produits sont marqués « démo » dans l'administration.\n";
echo "Pour les retirer tous : php database/seed-demo.php --purger\n";
