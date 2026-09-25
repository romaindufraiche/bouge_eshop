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
$isRelay = $order['fulfilment'] === Status::RELAY;
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
                            <?= e(Status::fulfilments()[$order['fulfilment']] ?? $order['fulfilment']) ?>
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

        <?php if (!$isPickup): ?>
            <?php /* L'étiquette d'abord, le suivi manuel ensuite : quand le
                     transporteur est branché, le second n'est qu'un
                     rattrapage. */ ?>
            <div class="admin-card">
                <h2 class="t-m">Étiquette</h2>

                <?php if (!empty($order['label_url'])): ?>
                    <p class="t-s">
                        <a class="btn btn--accent" href="<?= e((string) $order['label_url']) ?>"
                           target="_blank" rel="noopener">
                            Imprimer l'étiquette
                            <span class="sr-only">(nouvel onglet)</span>
                        </a>
                    </p>
                    <p class="t-xs muted" style="margin-top:1rem">
                        Envoi n° <code><?= e((string) $order['label_reference']) ?></code><br>
                        Imprimez, collez sur le colis, déposez. Le lien reste valable&nbsp;:
                        inutile de racheter une étiquette si l'impression rate.
                    </p>
                <?php elseif (!$carrierReady): ?>
                    <p class="t-s muted">
                        Aucun transporteur n'est branché. L'étiquette s'achète pour l'instant
                        sur le site du transporteur, en recopiant l'adresse ci-contre, et le
                        numéro de suivi se saisit plus bas.
                    </p>
                <?php elseif ($order['paid_at'] === null): ?>
                    <p class="t-s muted">
                        Cette commande n'est pas encore payée&nbsp;: rien ne part tant que le
                        paiement n'est pas confirmé.
                    </p>
                <?php else: ?>
                    <p class="field-help">
                        Achète l'étiquette chez <?= e($carrierName) ?> pour un colis de
                        <strong><?= e(number_format($parcelWeight / 1000, 2, ',', ' ')) ?> kg</strong>,
                        emballage compris, et passe la commande à «&nbsp;Expédiée&nbsp;».
                        <?php /* Une confirmation en deux temps : l'étiquette est
                                 facturée dès le clic, et rien ne la rembourse. */ ?>
                    </p>

                    <details class="confirm">
                        <summary class="btn btn--accent">Acheter l'étiquette</summary>
                        <div class="confirm__body">
                            <p class="t-s">
                                L'étiquette est facturée par le transporteur dès maintenant.
                                Vérifiez le poids sur les fiches produit s'il vous semble faux.
                            </p>
                            <form method="post" action="/admin/commandes/etiquette">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                <button class="btn btn--accent" type="submit">Confirmer l'achat</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <h2 class="t-m">Suivi du colis</h2>
                <p class="field-help">
                    Renseigné, le client le voit sur sa commande, dans son
                    espace. La date d'expédition est posée automatiquement.
                    L'achat d'une étiquette le remplit tout seul&nbsp;; ces champs
                    servent à le corriger, ou à saisir un envoi fait à la main.
                </p>

                <form method="post" action="/admin/commandes/suivi" class="stack">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">

                    <div class="field">
                        <label for="tracking_carrier">Transporteur</label>
                        <input type="text" id="tracking_carrier" name="tracking_carrier"
                               value="<?= e($order['tracking_carrier'] ?? '') ?>"
                               maxlength="60" placeholder="Colissimo, Mondial Relay…">
                    </div>

                    <div class="field">
                        <label for="tracking_number">Numéro de suivi</label>
                        <input type="text" id="tracking_number" name="tracking_number"
                               value="<?= e($order['tracking_number'] ?? '') ?>" maxlength="80">
                    </div>

                    <button class="btn btn--ghost" type="submit">Enregistrer le suivi</button>
                </form>

                <?php if (!empty($order['shipped_at'])): ?>
                    <p class="t-xs muted" style="margin-top:1rem">
                        Expédiée le <?= e(date('d/m/Y à H\hi', strtotime((string) $order['shipped_at']))) ?>.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <h2 class="t-m">Client</h2>
            <?php if (!empty($order['customer_id'])): ?>
                <p class="t-xs"><span class="pill">Compte client</span></p>
            <?php endif; ?>

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

            <?php if ($isRelay): ?>
                <?php /* Le colis part à l'adresse du point relais, pas à celle du
                         client : c'est celle-là qu'il faut recopier sur
                         l'étiquette, avec le code du point pour que le
                         transporteur sache où l'acheminer. */ ?>
                <p class="t-s">
                    <strong><?= e((string) $order['relay_name']) ?></strong><br>
                    <?= e((string) $order['relay_address']) ?><br>
                    <?= e((string) $order['relay_postal_code']) ?> <?= e((string) $order['relay_city']) ?>
                </p>
                <p class="t-xs muted" style="margin-top:1rem">
                    Transporteur : <?= e((string) $order['relay_operator']) ?><br>
                    Code du point : <code><?= e((string) $order['relay_code']) ?></code>
                </p>
                <p class="t-xs muted" style="margin-top:1rem">
                    Le client passera le retirer sur place&nbsp;; il reçoit un avis du
                    transporteur dès l'arrivée du colis.
                </p>
            <?php elseif ($isPickup): ?>
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
