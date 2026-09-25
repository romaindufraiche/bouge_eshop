<?php
/**
 * Catalogue : tout le matériel, une catégorie, un usage, une sélection ou
 * une recherche. La page est la même, seule l'entrée change.
 *
 * @var array<int, array<string,mixed>> $categories
 * @var array<int, array<string,mixed>> $products
 * @var array<string, mixed>|null       $category
 * @var string|null                     $usage
 * @var string|null                     $shortcut
 * @var string|null                     $shortcutTitle
 * @var string|null                     $shortcutDescription
 * @var string                          $search
 * @var string                          $tri
 * @var array<string, mixed>            $filtres
 */

use Bouge\Repository\ProductRepository;
use Bouge\Support\Usage;
use Bouge\Support\View;

$count = count($products);

// Intitulé et chapô, selon la porte d'entrée.
if ($shortcut !== null) {
    $heading = $shortcutTitle;
    $intro = $shortcutDescription;
} elseif ($search !== '') {
    $heading = 'Recherche';
    $intro = null;
} elseif ($category !== null) {
    $heading = $category['name'];
    $intro = $category['description'] ?: null;
} elseif ($usage !== null) {
    $heading = Usage::label($usage);
    $intro = Usage::descriptions()[$usage] ?? null;
} else {
    $heading = 'Tout le matériel';
    $intro = 'Bonnets, lunettes, accessoires et maillots : performance, style ou confort, il y a de tout.';
}

$categorieActive = $category['slug'] ?? '';
// Sur une page de catégorie, le filtre de catégorie est déjà joué par l'URL.
$categorieFiltre = $categorieActive !== '' ? $categorieActive : (string) ($_GET['categorie'] ?? '');
$usageFiltre = $usage ?? '';
$enMagasin = !empty($filtres['in_store']);
$enPromo = !empty($filtres['on_sale']);

// Base des liens du fil d'Ariane et du formulaire de filtres.
$action = $search !== '' || ($_GET['q'] ?? null) !== null
    ? '/recherche'
    : ($shortcut !== null
        ? '/boutique/selection/' . $shortcut
        : ($category !== null
            ? '/boutique/' . $category['slug']
            : ($usage !== null ? '/usage/' . $usage : '/boutique')));

$filtresActifs = ($categorieFiltre !== '' && $category === null)
    || ($usageFiltre !== '' && $usage === null)
    || ($enMagasin && $shortcut !== 'en-magasin')
    || ($enPromo && $shortcut !== 'promotions')
    || $tri !== 'nouveautes';
?>
<div class="wrap">
    <div class="section">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb">
                <li><a href="/">Accueil</a></li>
                <li aria-hidden="true">/</li>
                <?php if ($heading === 'Tout le matériel'): ?>
                    <li aria-current="page">Boutique</li>
                <?php else: ?>
                    <li><a href="/boutique">Boutique</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page"><?= e($heading) ?></li>
                <?php endif; ?>
            </ol>
        </nav>

        <header style="margin-top:1.5rem">
            <h1 class="t-xl"><?= e($heading) ?></h1>

            <?php if ($search !== ''): ?>
                <p class="t-m" style="margin-top:.75rem">
                    <?= (int) $count ?> résultat<?= $count > 1 ? 's' : '' ?> pour « <?= e($search) ?> ».
                </p>
            <?php elseif ($intro !== null): ?>
                <p class="muted" style="margin-top:.75rem;max-width:42rem"><?= e($intro) ?></p>
            <?php endif; ?>
        </header>

        <?php /* Le formulaire est en GET : les filtres restent dans l'adresse,
                 la page se recharge, se partage et se met en favori telle
                 quelle. Le bouton « Afficher » sert à ceux qui n'ont pas de
                 JavaScript — c'est-à-dire, ici, tout le monde. */ ?>
        <form class="filtres" method="get" action="<?= e($action) ?>" style="margin-top:2rem">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="q" value="<?= e($search) ?>">
            <?php endif; ?>

            <?php if ($category === null): ?>
                <div class="field">
                    <label for="filtre-categorie">Catégorie</label>
                    <select id="filtre-categorie" name="categorie">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $item): ?>
                            <option value="<?= e($item['slug']) ?>"<?= $categorieFiltre === $item['slug'] ? ' selected' : '' ?>>
                                <?= e($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($usage === null): ?>
                <div class="field">
                    <label for="filtre-usage">Usage</label>
                    <select id="filtre-usage" name="usage">
                        <option value="">Tous</option>
                        <?php foreach (Usage::all() as $slug => $libelle): ?>
                            <option value="<?= e($slug) ?>"<?= $usageFiltre === $slug ? ' selected' : '' ?>>
                                <?= e($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label for="filtre-tri">Trier par</label>
                <select id="filtre-tri" name="tri">
                    <?php foreach (ProductRepository::SORTS as $valeur => $libelle): ?>
                        <option value="<?= e($valeur) ?>"<?= $tri === $valeur ? ' selected' : '' ?>>
                            <?= e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtres__cases">
                <?php if ($shortcut !== 'en-magasin'): ?>
                    <label class="inline-check">
                        <input type="checkbox" name="dispo" value="magasin"<?= $enMagasin ? ' checked' : '' ?>>
                        <span>Disponible en magasin</span>
                    </label>
                <?php endif; ?>

                <?php if ($shortcut !== 'promotions'): ?>
                    <label class="inline-check">
                        <input type="checkbox" name="promo" value="1"<?= $enPromo ? ' checked' : '' ?>>
                        <span>En promotion</span>
                    </label>
                <?php endif; ?>
            </div>

            <div class="filtres__actions">
                <button class="btn btn--ghost" type="submit">Afficher</button>
                <?php if ($filtresActifs): ?>
                    <a class="link-quiet t-s" href="<?= e($action) ?>">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="t-s muted" style="margin-top:1.5rem" role="status">
            <?= (int) $count ?> article<?= $count > 1 ? 's' : '' ?>
        </p>

        <div style="margin-top:1rem">
            <?php if ($products === []): ?>
                <div class="vide">
                    <p class="t-m">Aucun article ne correspond.</p>
                    <p class="muted t-s">
                        Élargissez la recherche, ou repartez de
                        <a href="/boutique">tout le matériel</a>.
                    </p>
                </div>
            <?php else: ?>
                <?= View::partial('partials/product-grid', ['products' => $products]) ?>
            <?php endif; ?>
        </div>

        <?php /* Rebond vers les autres usages : c'est la question que se pose
                 vraiment un nageur, et elle relance la visite plutôt que de
                 la laisser finir en bas de page. */ ?>
        <section class="divider" style="margin-top:4rem;padding-top:2.5rem">
            <h2 class="t-s">Chercher par usage</h2>
            <ul class="filters" style="margin-top:1rem">
                <?php foreach (Usage::all() as $slug => $libelle): ?>
                    <li>
                        <a href="/usage/<?= e($slug) ?>"<?= $usage === $slug ? ' aria-current="page"' : '' ?>>
                            <?= e($libelle) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
