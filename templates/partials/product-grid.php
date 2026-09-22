<?php
/**
 * @var array<int, array<string, mixed>> $products
 */

use Bouge\Support\View;
?>
<?php if ($products === []): ?>
    <p class="muted" style="padding:4rem 0;text-align:center">
        Aucun produit dans cette catégorie pour le moment.
    </p>
<?php else: ?>
    <ul class="product-grid">
        <?php foreach ($products as $product): ?>
            <li><?= View::partial('partials/product-card', ['product' => $product]) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
