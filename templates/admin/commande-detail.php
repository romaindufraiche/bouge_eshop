<?php
/**
 * Détail d'une commande : ce qu'il faut préparer, et pour qui.
 *
 * Le contenu de la commande n'est pas modifiable : les libellés et les
 * montants sont figés au moment du paiement, pour que la facture reste
 * fidèle même si le produit change ensuite.
 *
 * @var array<string, mixed>  $order
 * @var array<string, string> $statuses
 */

use Bouge\Support\Csrf;
use Bouge\Support\Money;
use Bouge\Support\Status;

$isPickup = $order['fulfilment'] === Status::PICKUP;
?>
<header class="admin-head between">
    <div>
        <p class="t-xs"><a class="link-quiet" href="/admin/commandes">← Toutes les commandes</a></p>
        <h1 class="t-l">Commande <?= e($order['reference']) ?></h1>
        <p class="muted t-s">
            Passée le <?= e(date('d/m/Y à H\hi', strtotime((string) $order['created_at']))) ?>
            <?php if ($order['paid_at'] !== null): ?>
                · payée le <?= e(date('d/m/Y à H\hi', strtotime((string) $order['paid_at']))) ?>
            <?php endif; ?>
        </p>
    </div>
    <span class="pill pill--ink"><?= e(Status::orderLabel((string) $order['status'])) ?></span>
</header>

<div class="admin-columns admin-columns--aside">
    <section class="stack-l">
        <div class="admin-card">
            <h2 class="t-m">Articles</h2>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Article</th>
                        <th scope="col" class="ta-right">Prix unitaire</th>
                        <th scope="col" class="ta-right">Qté</th>
                        <th scope="col" class="ta-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <?= e($item['product_name']) ?>
                                <?php if (!empty($item['variant_label'])): ?>
                                    <span class="t-xs muted d-block"><?= e($item['variant_label']) ?></span>
                                <?php endif; ?>
                                <?php if ($item['product_id'] === null): ?>
                                    <?php /* Le produit a été supprimé du catalogue depuis :
                                             la ligne garde son libellé d'origine. */ ?>
                                    <span class="t-xs muted d-block">Produit retiré du catalogue</span>
                                <?php endif; ?>
                            </td>
                            <td class="ta-right nums t-s"><?= e(Money::format((int) $item['unit_price_cents'])) ?></td>
                            <td class="ta-right nums t-s"><?= (int) $item['quantity'] ?></td>
                            <td class="ta-right nums t-s"><?= e(Money::format((int) $item['line_total_cents'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3" class="ta-right">Sous-total</th>
                        <td class="ta-right nums"><?= e(Money::format((int) $order['subtotal_cents'])) ?></td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="3" class="ta-right">
                            <?= $isPickup ? 'Retrait' : 'Livraison' ?>
                        </th>
                        <td class="ta-right nums">
                            <?= (int) $order['shipping_cents'] === 0
                                ? 'Offert'
                                : e(Money::format((int) $order['shipping_cents'])) ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="3" class="ta-right">Total payé</th>
                        <td class="ta-right nums"><strong><?= e(Money::format((int) $order['total_cents'])) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="admin-card">
            <h2 class="t-m">Note interne</h2>
            <p class="field-help">Visible uniquement ici. Le client ne la voit jamais.</p>

            <form method="post" action="/admin/commandes/note" class="stack">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">

                <div class="field">
                    <label class="sr-only" for="admin_note">Note interne</label>
                    <textarea id="admin_note" name="admin_note" rows="4" maxlength="1000"><?= e($order['admin_note'] ?? '') ?></textarea>
                </div>

                <button class="btn btn--ghost" type="submit">Enregistrer la note</button>
            </form>
        </div>
    </section>

    <aside class="stack-l">
        <div class="admin-card">
            <h2 class="t-m">Avancement</h2>

            <form method="post" action="/admin/commandes/statut" class="stack">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">

                <div class="field">
                    <label for="status">Statut de la commande</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $order['status'] === $value ? ' selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="btn" type="submit">Mettre à jour</button>
            </form>

            <p class="field-help" style="margin-top:1rem">
                Le passage à « payée » est fait automatiquement par Stripe : il n'y a
                rien à cocher à la réception du paiement.
            </p>
        </div>

        <div class="admin-card">
            <h2 class="t-m">Client</h2>
            <p class="t-s stack-s">
                <strong><?= e($order['customer_name']) ?></strong><br>
                <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a>
                <?php if (!empty($order['phone'])): ?>
                    <br><?= e($order['phone']) ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="admin-card">
            <h2 class="t-m"><?= $isPickup ? 'Retrait' : 'Livraison' ?></h2>

            <?php if ($isPickup): ?>
                <?php if ($order['pickup_name'] === null): ?>
                    <p class="t-s muted">Le point de retrait choisi a été supprimé depuis.</p>
                <?php else: ?>
                    <p class="t-s">
                        <strong><?= e($order['pickup_name']) ?></strong><br>
                        <?= e($order['pickup_address']) ?><br>
                        <?= e($order['pickup_postal_code']) ?> <?= e($order['pickup_city']) ?>
                        <?php if (!empty($order['pickup_hours'])): ?>
                            <br><span class="muted"><?= e($order['pickup_hours']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="t-s">
                    <?= e($order['shipping_address_line1']) ?><br>
                    <?php if (!empty($order['shipping_address_line2'])): ?>
                        <?= e($order['shipping_address_line2']) ?><br>
                    <?php endif; ?>
                    <?= e($order['shipping_postal_code']) ?> <?= e($order['shipping_city']) ?><br>
                    <?= e($order['shipping_country'] === 'FR' ? 'France' : $order['shipping_country']) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($order['stripe_payment_intent_id'])): ?>
            <div class="admin-card">
                <h2 class="t-m">Paiement</h2>
                <p class="t-xs muted">
                    Référence Stripe :<br>
                    <code><?= e($order['stripe_payment_intent_id']) ?></code>
                </p>
                <p class="field-help">
                    À communiquer au support Stripe en cas de litige ou de remboursement.
                </p>
            </div>
        <?php endif; ?>
    </aside>
</div>
