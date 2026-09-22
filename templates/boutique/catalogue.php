<?php
/**
 * Catalogue : tout le matériel, ou une catégorie.
 *
 * @var array<int, array<string,mixed>> $categories
 * @var array<int, array<string,mixed>> $products
 * @var array<string, mixed>|null       $category   null sur « Tout le matériel »
 */

use Bouge\Support\View;

$heading = $category === null ? 'Tout le matériel' : $category['name'];
$activeSlug = $category === null ? null : $category['slug'];
$count = count($products);
?>
<div class="wrap">
    <div class="section">
        <h1 class="t-xl"><?= e($heading) ?></h1>

        <?php if ($category !== null && !empty($category['description'])): ?>
            <p class="muted" style="margin-top:.75rem;max-width:40rem"><?= e($category['description']) ?></p>
        <?php else: ?>
            <p class="muted" style="margin-top:.75rem">
                <?= (int) $count ?> produit<?= $count > 1 ? 's' : '' ?> en ligne.
            </p>
        <?php endif; ?>

        <?php /* Chaque filtre est un vrai lien vers une page dédiée : l'URL est
                 partageable et chaque catégorie a ses propres balises SEO. */ ?>
        <nav aria-label="Filtrer par catégorie" style="margin-top:2rem">
            <ul class="filters">
                <li>
                    <a href="/boutique" <?= $activeSlug === null ? 'aria-current="page"' : '' ?>>Tout</a>
                </li>
                <?php foreach ($categories as $item): ?>
                    <li>
                        <a href="/boutique/<?= e($item['slug']) ?>"
                           <?= $activeSlug === $item['slug'] ? 'aria-current="page"' : '' ?>><?= e($item['name']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div style="margin-top:3rem">
            <?= View::partial('partials/product-grid', ['products' => $products]) ?>
        </div>
    </div>
</div>
