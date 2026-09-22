<?php
/**
 * Vignette produit du catalogue et de la page d'accueil.
 *
 * @var array<string, mixed> $product
 */

use Bouge\Support\Pricing;
use Bouge\Support\View;

$price = Pricing::effective($product);
$external = ($product['external_url'] ?? null) !== null && $product['external_url'] !== '';
$cover = $product['cover'] ?? null;
?>
<article class="product-card">
    <a href="/produit/<?= e($product['slug']) ?>">
        <div class="product-card__media">
            <?php if ($cover !== null): ?>
                <img src="<?= e($cover['url']) ?>" alt="<?= e($cover['alt']) ?>" loading="lazy" width="800" height="1000">
            <?php else: ?>
                <span class="muted t-s" style="display:grid;place-items:center;height:100%">Photo à venir</span>
            <?php endif; ?>

            <?php if ($price->onSale() && !$external): ?>
                <span class="pill pill--accent product-card__badge">Promo</span>
            <?php endif; ?>

            <?php if (!empty($product['available_in_store'])): ?>
                <span class="pill pill--ink product-card__badge product-card__badge--right">En magasin</span>
            <?php endif; ?>
        </div>

        <div class="product-card__body">
            <?php if (!empty($product['category_name'])): ?>
                <p class="eyebrow"><?= e($product['category_name']) ?></p>
            <?php endif; ?>

            <h3 class="product-card__name"><?= e($product['name']) ?></h3>

            <?php if ($external): ?>
                <?php /* Pas de prix pour un produit vendu ailleurs : il est fixé
                         par le revendeur et nous n'en avons pas la maîtrise. */ ?>
                <p class="t-s muted">Vendu sur <?= e($product['external_label'] ?: parse_url((string) $product['external_url'], PHP_URL_HOST) ?: 'le revendeur') ?></p>
            <?php else: ?>
                <?= View::partial('partials/price', ['price' => $price]) ?>
            <?php endif; ?>
        </div>
    </a>
</article>
