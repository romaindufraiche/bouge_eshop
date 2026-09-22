<?php

declare(strict_types=1);

/**
 * Génère l'aperçu statique publié par GitHub Pages, dans docs/.
 *
 *   php -S localhost:8000 -t public dev-server.php &
 *   php bin/apercu.php [http://localhost:8000]
 *
 * Le site est en PHP : un hébergeur de fichiers statiques ne peut pas
 * l'exécuter. On enregistre donc le HTML qu'il produit, page par page, et on
 * réécrit les liens internes en fichiers .html voisins. Le résultat est une
 * vitrine : le panier, le paiement et l'administration ont besoin du serveur
 * et ne fonctionnent pas — un bandeau le dit en haut de chaque page.
 */

use Bouge\Support\Database;
use Bouge\Support\Status;

require dirname(__DIR__) . '/src/autoload.php';

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$racine = dirname(__DIR__);
$dest = $racine . '/docs';

// --- Pages à enregistrer ---------------------------------------------------------
// Chemin du site => fichier de l'aperçu.

$pages = [
    '/'                 => 'index.html',
    '/boutique'         => 'boutique.html',
    '/panier'           => 'panier.html',
    '/livraison'        => 'livraison.html',
    '/cgv'              => 'cgv.html',
    '/mentions-legales' => 'mentions-legales.html',
];

foreach (Database::all('SELECT slug FROM categories ORDER BY position ASC') as $categorie) {
    $pages['/boutique/' . $categorie['slug']] = 'categorie-' . $categorie['slug'] . '.html';
}

foreach (Database::all('SELECT slug FROM products WHERE status = ?', [Status::PRODUCT_PUBLISHED]) as $produit) {
    $pages['/produit/' . $produit['slug']] = 'produit-' . $produit['slug'] . '.html';
}

$banniere = <<<'HTML'
<div class="apercu-banniere">
  <p><strong>Aperçu statique</strong> — le design et la navigation sont réels.
     Le panier, le paiement et l'administration ont besoin d'un serveur PHP :
     ils ne fonctionnent pas ici.</p>
</div>
HTML;

$style = <<<'HTML'
<style>
.apercu-banniere {
  background: #232323; color: #fffbe8;
  font: 400 0.8125rem/1.5 'Manrope', system-ui, sans-serif;
  padding: 0.75rem 1.25rem; text-align: center;
}
.apercu-banniere p { margin: 0 auto; max-width: 52rem; }
.apercu-banniere strong { color: #fffbe8; }
</style>
HTML;

$script = <<<'HTML'
<script>
// Les formulaires (ajout au panier, commande) ont besoin du serveur : on les
// neutralise plutôt que de laisser le visiteur sur une erreur.
document.addEventListener('submit', function (event) {
  event.preventDefault();
  var b = document.querySelector('.apercu-banniere');
  if (b) { b.scrollIntoView({ behavior: 'smooth' }); b.animate(
    [{opacity:1},{opacity:.35},{opacity:1}], {duration:600, iterations:2}); }
});
</script>
HTML;

// --- Enregistrement ----------------------------------------------------------------

if (is_dir($dest)) {
    // Suppression récursive du dossier précédent : il ne doit rester aucune
    // page d'un produit supprimé entre deux générations.
    $fichiers = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($fichiers as $fichier) {
        $fichier->isDir() ? rmdir($fichier->getPathname()) : unlink($fichier->getPathname());
    }
} else {
    mkdir($dest, 0755, true);
}

$references = [];

foreach ($pages as $chemin => $fichier) {
    $html = @file_get_contents($base . $chemin);

    if ($html === false) {
        exit("Page injoignable : {$base}{$chemin}\nLe serveur de développement tourne-t-il ?\n");
    }

    // Fichiers statiques : on retire le suffixe de version et le slash initial.
    $html = preg_replace('#(src|href)="/assets/([^"?]+)(\?v=\d+)?"#', '$1="assets/$2"', $html) ?? $html;

    // Liens internes : vers le fichier correspondant de l'aperçu, ou, si la
    // page n'y figure pas (commande, admin, galerie ?photo=), vers elle-même
    // plutôt que vers une erreur 404.
    $html = preg_replace_callback(
        '#href="(/[^"]*)"#',
        static function (array $m) use ($pages, $fichier): string {
            $cible = rtrim(strtok($m[1], '?') ?: '/', '/') ?: '/';

            return 'href="' . ($pages[$cible] ?? $fichier) . '"';
        },
        $html
    ) ?? $html;

    // La balise canonique et les images sociales désignent le serveur de
    // développement : inutiles ici, et trompeuses si elles restent.
    $html = preg_replace('#\s*<link rel="canonical"[^>]*>#', '', $html) ?? $html;
    $html = str_replace($base . '/assets/', 'assets/', $html);

    $html = str_replace('<body class="boutique">', "<body class=\"boutique\">\n" . $banniere, $html);
    $html = str_replace('</head>', $style . "\n</head>", $html);
    $html = str_replace('</body>', $script . "\n</body>", $html);

    file_put_contents($dest . '/' . $fichier, $html);

    preg_match_all('#(?:src|href)="(assets/[^"?]+)"#', $html, $trouves);
    $references = array_merge($references, $trouves[1]);
}

// Les polices sont appelées par la feuille de style, pas par le HTML.
$css = (string) file_get_contents($racine . '/public/assets/css/site.css');
preg_match_all("#url\('\.\./([^']+)'\)#", $css, $trouves);
foreach ($trouves[1] as $chemin) {
    $references[] = 'assets/' . $chemin;
}

// On n'emporte que les fichiers réellement utilisés : le dépôt n'a pas à
// porter deux fois les visuels qu'aucune page n'appelle.
foreach (array_unique($references) as $chemin) {
    $source = $racine . '/public/' . $chemin;

    if (!is_file($source)) {
        continue;
    }

    $cible = $dest . '/' . $chemin;
    @mkdir(dirname($cible), 0755, true);
    copy($source, $cible);
}

// Sans ce fichier, GitHub Pages fait passer le dossier par Jekyll, qui ignore
// les fichiers et dossiers commençant par un tiret bas.
touch($dest . '/.nojekyll');

printf("✓ Aperçu : %d pages, %d fichiers statiques dans docs/\n", count($pages), count(array_unique($references)));
