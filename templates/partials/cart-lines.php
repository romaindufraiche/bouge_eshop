<?php
/**
 * Articles du panier.
 *
 * @var array<int, array<string,mixed>> $lines
 * @var bool                            $editable false dans le récapitulatif
 */

use Bouge\Support\Csrf;
use Bouge\Support\Money;

$editable = $editable ?? true;
?>
<ul class="cart-lines">
    <?php foreach ($lines as $line): ?>
        <li>
            <a class="cart-lines__media" href="/produit/<?= e($line['slug']) ?>">
                <?php if ($line['image_url'] !== null): ?>
                    <img src="<?= e($line['image_url']) ?>" alt="<?= e($line['name']) ?>"
                         loading="lazy" width="200" height="200">
                <?php endif; ?>
            </a>

            <div class="cart-lines__body">
                <a href="/produit/<?= e($line['slug']) ?>" style="text-decoration:none"><?= e($line['name']) ?></a>

                <?php if ($line['variant_label'] !== null): ?>
                    <p class="t-s muted"><?= e($line['variant_label']) ?></p>
                <?php endif; ?>

                <p class="t-s muted nums">
                    <?= e(Money::format($line['unit_price_cents'])) ?> l'unité
                    <?php if ($line['compare_at_cents'] !== null): ?>
                        <s style="margin-left:.5rem"><?= e(Money::format($line['compare_at_cents'])) ?></s>
                    <?php endif; ?>
                </p>

                <?php if ($editable): ?>
                    <div class="row" style="margin-top:.75rem;gap:1rem">
                        <form method="post" action="/panier/modifier" class="row" style="gap:.5rem">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                            <input type="hidden" name="variant_id" value="<?= $line['variant_id'] === null ? '' : (int) $line['variant_id'] ?>">
                            <label class="sr-only" for="qte-<?= (int) $line['product_id'] ?>-<?= (int) ($line['variant_id'] ?? 0) ?>">
                                Quantité pour <?= e($line['name']) ?>
                            </label>
                            <select id="qte-<?= (int) $line['product_id'] ?>-<?= (int) ($line['variant_id'] ?? 0) ?>"
                                    name="quantity" style="width:auto;margin-top:0" onchange="this.form.submit()">
                                <?php for ($q = 1; $q <= max((int) $line['available_stock'], 1); $q++): ?>
                                    <option value="<?= $q ?>" <?= $q === (int) $line['quantity'] ? 'selected' : '' ?>><?= $q ?></option>
                                <?php endfor; ?>
                            </select>
                            <?php /* Bouton visible si JavaScript est désactivé :
                                     le `onchange` ci-dessus n'est qu'un confort. */ ?>
                            <noscript><button type="submit" class="btn" style="padding:.4rem 1rem">Mettre à jour</button></noscript>
                        </form>

                        <form method="post" action="/panier/retirer">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                            <input type="hidden" name="variant_id" value="<?= $line['variant_id'] === null ? '' : (int) $line['variant_id'] ?>">
                            <button type="submit" class="link-quiet">Retirer</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="t-s muted" style="margin-top:.25rem">Quantité : <?= (int) $line['quantity'] ?></p>
                <?php endif; ?>
            </div>

            <p class="cart-lines__total"><?= e(Money::format($line['line_total_cents'])) ?></p>
        </li>
    <?php endforeach; ?>
</ul>
