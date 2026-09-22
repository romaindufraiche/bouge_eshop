<?php
/**
 * Affichage d'un prix, avec prix barré et remise en cas de promotion.
 *
 * @var \Bouge\Support\Price $price
 * @var string|null          $size 'lg' pour la fiche produit
 */

use Bouge\Support\Money;

$classes = 'price' . ($price->onSale() ? ' price--sale' : '') . (($size ?? '') === 'lg' ? ' price--lg' : '');
?>
<span class="<?= e($classes) ?>">
    <span class="price__now"><?= e(Money::format($price->cents)) ?></span>
    <?php if ($price->onSale() && $price->compareAtCents !== null): ?>
        <s class="price__was"><?= e(Money::format($price->compareAtCents)) ?></s>
        <?php if ($price->discountPercent !== null): ?>
            <span class="pill pill--accent">−<?= (int) $price->discountPercent ?> %</span>
        <?php endif; ?>
    <?php endif; ?>
</span>
