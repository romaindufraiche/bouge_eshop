<?php
/**
 * @var array<string, mixed> $order
 * @var array<string, mixed> $shop
 */

use Bouge\Support\Money;
use Bouge\Support\Status;

$isPickup = $order['fulfilment'] === Status::PICKUP;
?>
<div class="wrap">
    <div class="section">
        <p class="eyebrow">Commande <?= e($order['reference']) ?></p>
        <h1 class="t-xl" style="margin-top:.5rem">Merci, c'est confirmé.</h1>

        <p class="muted" style="margin-top:1rem;max-width:36rem">
            Un récapitulatif part à l'instant sur <?= e($order['email']) ?>.
            <?= $isPickup
                ? 'Nous vous prévenons dès que la commande est prête à être retirée.'
                : 'Votre commande part sous 48 heures ouvrées.' ?>
        </p>

        <div class="grid grid--2" style="margin-top:3rem">
            <section>
                <h2 class="eyebrow"><?= $isPickup ? 'Retrait sur place' : 'Livraison' ?></h2>
                <address class="t-s" style="margin-top:.75rem;font-style:normal">
                    <?php if ($isPickup): ?>
                        <?php if (!empty($order['pickup_name'])): ?>
                            <strong><?= e($order['pickup_name']) ?></strong><br>
                            <?= e($order['pickup_address']) ?><br>
                            <?= e($order['pickup_postal_code']) ?> <?= e($order['pickup_city']) ?>
                            <?php if (!empty($order['pickup_hours'])): ?>
                                <br><span class="muted"><?= e($order['pickup_hours']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            Point de retrait à confirmer.
                        <?php endif; ?>
                    <?php else: ?>
                        <?= e($order['customer_name']) ?><br>
                        <?= e($order['shipping_address_line1']) ?>
                        <?php if (!empty($order['shipping_address_line2'])): ?>
                            <br><?= e($order['shipping_address_line2']) ?>
                        <?php endif; ?>
                        <br><?= e($order['shipping_postal_code']) ?> <?= e($order['shipping_city']) ?>
                    <?php endif; ?>
                </address>
            </section>

            <section>
                <h2 class="eyebrow">Montant</h2>
                <dl class="t-s" style="margin-top:.75rem">
                    <div style="display:flex;justify-content:space-between">
                        <dt class="muted">Sous-total</dt>
                        <dd class="nums" style="margin:0"><?= e(Money::format((int) $order['subtotal_cents'])) ?></dd>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:.5rem">
                        <dt class="muted">Livraison</dt>
                        <dd class="nums" style="margin:0">
                            <?= (int) $order['shipping_cents'] === 0 ? 'Offerte' : e(Money::format((int) $order['shipping_cents'])) ?>
                        </dd>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:.5rem;padding-top:.5rem;border-top:1px solid var(--line)">
                        <dt>Total payé</dt>
                        <dd class="nums" style="margin:0"><?= e(Money::format((int) $order['total_cents'])) ?></dd>
                    </div>
                </dl>
            </section>
        </div>

        <section style="margin-top:3rem">
            <h2 class="eyebrow">Articles</h2>
            <ul class="cart-lines" style="margin-top:1rem">
                <?php foreach ($order['items'] as $item): ?>
                    <li style="justify-content:space-between">
                        <span class="t-s">
                            <?= e($item['product_name']) ?>
                            <?php if (!empty($item['variant_label'])): ?>
                                <span class="muted">— <?= e($item['variant_label']) ?></span>
                            <?php endif; ?>
                            <span class="muted">× <?= (int) $item['quantity'] ?></span>
                        </span>
                        <span class="t-s nums"><?= e(Money::format((int) $item['line_total_cents'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <p style="margin-top:2.5rem"><a class="btn" href="/boutique">Continuer mes achats</a></p>
    </div>
</div>
