<?php
/**
 * Détail d'une commande du client, avec son avancement.
 *
 * @var array<string, mixed> $commande
 */

use Bouge\Support\Money;
use Bouge\Support\Status;

$estRetrait = $commande['fulfilment'] === Status::PICKUP;

// Étapes de l'avancement : la dernière dépend du mode de remise choisi.
$etapes = [
    Status::ORDER_PAID      => 'Payée',
    Status::ORDER_PREPARING => 'En préparation',
    $estRetrait ? Status::ORDER_COLLECTED : Status::ORDER_SHIPPED
        => $estRetrait ? 'Retirée' : 'Expédiée',
];

$ordre = array_keys($etapes);
$position = array_search($commande['status'], $ordre, true);
$annulee = $commande['status'] === Status::ORDER_CANCELLED;
?>
<div class="wrap">
    <div class="section">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb">
                <li><a href="/compte">Mon compte</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page">Commande <?= e($commande['reference']) ?></li>
            </ol>
        </nav>

        <h1 class="t-xl" style="margin-top:1.5rem">Commande <?= e($commande['reference']) ?></h1>
        <p class="muted t-s" style="margin-top:.5rem">
            Passée le <?= e(date('d/m/Y', strtotime((string) $commande['created_at']))) ?>
            <?php if ($commande['paid_at'] !== null): ?>
                · payée le <?= e(date('d/m/Y', strtotime((string) $commande['paid_at']))) ?>
            <?php endif; ?>
        </p>

        <?php if ($annulee): ?>
            <p class="notice notice--accent" style="margin-top:2rem" role="status">
                <strong>Commande annulée.</strong>
                Si un montant a été débité, il est remboursé sous quelques jours.
            </p>
        <?php else: ?>
            <?php /* L'avancement en trois étapes : où en est la commande, et
                     ce qu'il reste à attendre. */ ?>
            <ol class="etapes" style="margin-top:2rem">
                <?php foreach ($etapes as $statut => $libelle): ?>
                    <?php
                    $index = array_search($statut, $ordre, true);
                    $atteinte = $position !== false && $index <= $position;
                    ?>
                    <li class="<?= $atteinte ? 'est-atteinte' : '' ?>">
                        <span class="etapes__puce" aria-hidden="true"></span>
                        <span class="etapes__libelle"><?= e($libelle) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="compte" style="margin-top:2.5rem">
            <section>
                <div class="admin-card" style="margin-top:0">
                    <h2 class="t-m">Articles</h2>
                    <ul class="cart-lines" style="margin-top:1rem">
                        <?php foreach ($commande['items'] as $ligne): ?>
                            <li>
                                <?php if (!empty($ligne['image_url'])): ?>
                                    <div class="cart-lines__media">
                                        <img src="<?= e($ligne['image_url']) ?>" alt="" width="200" height="200" loading="lazy">
                                    </div>
                                <?php endif; ?>

                                <div class="cart-lines__body">
                                    <p><strong><?= e($ligne['product_name']) ?></strong></p>
                                    <?php if (!empty($ligne['variant_label'])): ?>
                                        <p class="t-s muted"><?= e($ligne['variant_label']) ?></p>
                                    <?php endif; ?>
                                    <p class="t-s muted">
                                        <?= (int) $ligne['quantity'] ?> × <?= e(Money::format((int) $ligne['unit_price_cents'])) ?>
                                    </p>
                                </div>

                                <div class="cart-lines__total">
                                    <?= e(Money::format((int) $ligne['line_total_cents'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php /* La classe va sur le conteneur, pas sur la liste :
                             c'est ce que la feuille de style attend. */ ?>
                    <div class="summary" style="margin-top:1.5rem;border:0;padding:0">
                    <dl>
                        <div>
                            <dt>Sous-total</dt>
                            <dd><?= e(Money::format((int) $commande['subtotal_cents'])) ?></dd>
                        </div>
                        <div>
                            <dt><?= $estRetrait ? 'Retrait' : 'Livraison' ?></dt>
                            <dd><?= (int) $commande['shipping_cents'] === 0 ? 'Offert' : e(Money::format((int) $commande['shipping_cents'])) ?></dd>
                        </div>
                        <div class="summary__total">
                            <dt>Total</dt>
                            <dd><strong><?= e(Money::format((int) $commande['total_cents'])) ?></strong></dd>
                        </div>
                    </dl>
                    </div>
                </div>
            </section>

            <aside class="stack-l">
                <div class="admin-card" style="margin-top:0">
                    <h2 class="t-m"><?= $estRetrait ? 'Retrait' : 'Livraison' ?></h2>

                    <?php if ($estRetrait): ?>
                        <?php if ($commande['pickup_name'] === null): ?>
                            <p class="t-s muted">Le point de retrait n'est plus renseigné. Écrivez-nous.</p>
                        <?php else: ?>
                            <p class="t-s">
                                <strong><?= e($commande['pickup_name']) ?></strong><br>
                                <?= e($commande['pickup_address']) ?><br>
                                <?= e($commande['pickup_postal_code']) ?> <?= e($commande['pickup_city']) ?>
                                <?php if (!empty($commande['pickup_hours'])): ?>
                                    <br><span class="muted"><?= e($commande['pickup_hours']) ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="t-s">
                            <?= e($commande['customer_name']) ?><br>
                            <?= e($commande['shipping_address_line1']) ?><br>
                            <?php if (!empty($commande['shipping_address_line2'])): ?>
                                <?= e($commande['shipping_address_line2']) ?><br>
                            <?php endif; ?>
                            <?= e($commande['shipping_postal_code']) ?> <?= e($commande['shipping_city']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($commande['tracking_number'])): ?>
                        <div class="divider" style="margin-top:1.25rem;padding-top:1.25rem">
                            <p class="eyebrow">Suivi du colis</p>
                            <p class="t-s" style="margin-top:.5rem">
                                <?= e($commande['tracking_carrier'] ?: 'Transporteur') ?><br>
                                <span class="nums"><?= e($commande['tracking_number']) ?></span>
                            </p>
                            <?php if (!empty($commande['shipped_at'])): ?>
                                <p class="t-xs muted" style="margin-top:.35rem">
                                    Expédiée le <?= e(date('d/m/Y', strtotime((string) $commande['shipped_at']))) ?>.
                                </p>
                            <?php endif; ?>
                            <p class="field-help">
                                Reportez ce numéro sur le site du transporteur pour
                                situer le colis.
                            </p>
                        </div>
                    <?php elseif (!$estRetrait && !$annulee): ?>
                        <p class="field-help" style="margin-top:1rem">
                            Le numéro de suivi apparaîtra ici dès l'expédition.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="admin-card" style="margin-top:0">
                    <h2 class="t-m">Une question ?</h2>
                    <p class="t-s muted">
                        Citez la référence <strong><?= e($commande['reference']) ?></strong>,
                        on retrouve tout de suite.
                    </p>
                    <p style="margin-top:1rem">
                        <a class="btn btn--ghost btn--sm" href="mailto:<?= e($shop['email']) ?>">Nous écrire</a>
                    </p>
                </div>
            </aside>
        </div>
    </div>
</div>
