<?php
/**
 * @var array<string, mixed>            $shop
 * @var array<int, array<string,mixed>> $points
 */

use Bouge\Support\Money;
use Bouge\Support\Shipping;

$flatRate = (int) $shop['shipping']['flat_rate_cents'];
$freeAbove = $shop['shipping']['free_above_cents'];
$relayRate = $shop['shipping']['relay_cents'];
?>
<div class="wrap wrap--narrow">
    <article class="section">
        <h1 class="t-xl">Livraison et retrait</h1>

        <div class="stack-l muted" style="margin-top:2.5rem">
            <section>
                <h2 class="t-s" style="color:var(--ink)">Livraison en France</h2>
                <p style="margin-top:.5rem">
                    Frais de port : <?= e(Money::format($flatRate)) ?><?php if ($freeAbove !== null): ?>,
                    offerts à partir de <?= e(Money::format((int) $freeAbove)) ?> d'achat<?php endif; ?>.
                    Les commandes partent sous 48 heures ouvrées.
                </p>
            </section>

            <?php if (Shipping::relayAvailable()): ?>
                <section>
                    <h2 class="t-s" style="color:var(--ink)">Livraison en point relais</h2>
                    <p style="margin-top:.5rem">
                        Frais de port : <?= e(Money::format((int) $relayRate)) ?><?php if ($freeAbove !== null): ?>,
                        offerts au même seuil<?php endif; ?>. Vous choisissez le commerce qui vous
                        arrange au moment de commander, et le transporteur vous prévient dès que
                        le colis y est arrivé. Comptez une pièce d'identité pour le retirer.
                    </p>
                </section>
            <?php endif; ?>

            <?php if ($points !== []): ?>
                <section>
                    <h2 class="t-s" style="color:var(--ink)">Retrait au concept store</h2>
                    <p style="margin-top:.5rem">
                        Sans frais. Vous recevez un courriel dès que la commande est prête.
                    </p>
                    <ul style="list-style:none;margin:1rem 0 0;padding:0" class="stack">
                        <?php foreach ($points as $point): ?>
                            <li style="border:1px solid var(--line);border-radius:var(--radius-surface);padding:1rem">
                                <p style="color:var(--ink);font-weight:600"><?= e($point['name']) ?></p>
                                <p class="t-s" style="margin-top:.25rem">
                                    <?= e($point['address_line1']) ?>
                                    <?php if (!empty($point['address_line2'])): ?><br><?= e($point['address_line2']) ?><?php endif; ?>
                                    <br><?= e($point['postal_code']) ?> <?= e($point['city']) ?>
                                </p>
                                <?php if (!empty($point['hours'])): ?>
                                    <p class="t-s" style="margin-top:.25rem"><?= e($point['hours']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Retours</h2>
                <p style="margin-top:.5rem">
                    Quatorze jours pour changer d'avis. Les conditions détaillées figurent dans
                    les <a href="/cgv">conditions générales de vente</a>.
                </p>
            </section>
        </div>
    </article>
</div>
