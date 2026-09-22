<?php
/**
 * Récapitulatif chiffré.
 *
 * @var int      $subtotalCents
 * @var int|null $shippingCents null tant que le mode de remise n'est pas choisi
 * @var string   $actions       HTML des boutons
 */

use Bouge\Support\Money;
use Bouge\Support\Shipping;

$missing = Shipping::missingForFree($subtotalCents);
$total = $subtotalCents + ($shippingCents ?? 0);
?>
<div class="summary">
    <h2 class="t-s">Récapitulatif</h2>

    <dl>
        <div>
            <dt>Sous-total</dt>
            <dd><?= e(Money::format($subtotalCents)) ?></dd>
        </div>
        <div>
            <dt>Livraison</dt>
            <dd>
                <?php if ($shippingCents === null): ?>
                    <span class="muted">Calculée à l'étape suivante</span>
                <?php elseif ($shippingCents === 0): ?>
                    Offerte
                <?php else: ?>
                    <?= e(Money::format($shippingCents)) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div class="summary__total">
            <dt>Total</dt>
            <dd>
                <?php if ($shippingCents === null): ?>
                    à partir de <?= e(Money::format($subtotalCents)) ?>
                <?php else: ?>
                    <?= e(Money::format($total)) ?>
                <?php endif; ?>
            </dd>
        </div>
    </dl>

    <?php if ($missing !== null): ?>
        <p class="t-s muted" style="margin-top:1rem">
            Plus que <?= e(Money::format($missing)) ?> pour la livraison offerte.
        </p>
    <?php endif; ?>

    <div style="margin-top:1.5rem"><?= $actions ?></div>
</div>
