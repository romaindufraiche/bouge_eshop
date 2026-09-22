<?php
/**
 * Tunnel de commande.
 *
 * @var array<string, mixed>            $cart
 * @var array<int, array<string,mixed>> $points
 * @var array<string, string>           $errors
 * @var array<string, string>           $values
 * @var string|null                     $message
 * @var string                          $fulfilment
 * @var int                             $shippingCents
 * @var bool                            $stripeReady
 * @var array<string, mixed>            $shop
 */

use Bouge\Support\Csrf;
use Bouge\Support\Money;
use Bouge\Support\Shipping;
use Bouge\Support\Status;
use Bouge\Support\View;

$v = static fn (string $key): string => $values[$key] ?? '';
$err = static fn (string $key): ?string => $errors[$key] ?? null;

$deliveryCost = Shipping::cents($cart['subtotal_cents'], Status::DELIVERY);
?>
<div class="wrap">
    <div class="section">
        <h1 class="t-xl">Votre commande</h1>

        <?php if (!$stripeReady): ?>
            <p class="notice notice--accent" style="margin-top:1.5rem">
                <strong>Paiement non configuré.</strong>
                Renseignez <code>stripe.secret_key</code> dans <code>config/config.php</code>
                pour activer le règlement par carte.
            </p>
        <?php endif; ?>

        <?php if ($cart['empty']): ?>
            <p class="muted" style="margin-top:2rem">Votre panier est vide : il n'y a rien à commander.</p>
            <p style="margin-top:1.5rem"><a class="btn" href="/boutique">Voir le catalogue</a></p>
        <?php else: ?>
            <form method="post" action="/commande" class="grid grid--aside" style="margin-top:2.5rem">
                <?= Csrf::field() ?>

                <div class="stack-l">
                    <?php if ($message !== null): ?>
                        <p class="notice notice--accent" role="alert"><?= e($message) ?></p>
                    <?php endif; ?>

                    <?= View::partial('partials/cart-issues', ['issues' => $cart['issues']]) ?>

                    <section class="stack">
                        <h2 class="eyebrow">Vos coordonnées</h2>

                        <label class="field">
                            <span class="field__label">Nom et prénom</span>
                            <input type="text" name="customer_name" autocomplete="name" required
                                   value="<?= e($v('customer_name')) ?>"
                                   <?= $err('customer_name') ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($err('customer_name')): ?>
                                <span class="field-error"><?= e($err('customer_name')) ?></span>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span class="field__label">Adresse électronique</span>
                            <span class="field__hint">La confirmation de commande y sera envoyée.</span>
                            <input type="email" name="email" autocomplete="email" required
                                   value="<?= e($v('email')) ?>"
                                   <?= $err('email') ? 'aria-invalid="true"' : '' ?>>
                            <?php if ($err('email')): ?>
                                <span class="field-error"><?= e($err('email')) ?></span>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span class="field__label">Téléphone <span class="field__optional">(facultatif)</span></span>
                            <input type="tel" name="phone" autocomplete="tel" value="<?= e($v('phone')) ?>">
                        </label>
                    </section>

                    <section class="stack">
                        <h2 class="eyebrow">Livraison ou retrait</h2>

                        <?php /* Les deux blocs de champs sont dans la page ; c'est
                                 le CSS qui masque celui qui ne correspond pas au
                                 choix, sans JavaScript. */ ?>
                        <div class="grid grid--2">
                            <label class="choice">
                                <input type="radio" name="fulfilment" id="mode-livraison"
                                       value="<?= e(Status::DELIVERY) ?>"
                                       <?= $fulfilment !== Status::PICKUP ? 'checked' : '' ?>>
                                <span>
                                    <span class="choice__title">Livraison à domicile</span>
                                    <span class="choice__detail">
                                        <?= $deliveryCost === 0 ? 'Offerte' : e(Money::format($deliveryCost)) ?>
                                    </span>
                                </span>
                            </label>

                            <label class="choice">
                                <input type="radio" name="fulfilment" id="mode-retrait"
                                       value="<?= e(Status::PICKUP) ?>"
                                       <?= $fulfilment === Status::PICKUP ? 'checked' : '' ?>
                                       <?= $points === [] ? 'disabled' : '' ?>>
                                <span>
                                    <span class="choice__title">Retrait sur place</span>
                                    <span class="choice__detail">
                                        <?= $points === [] ? 'Aucun point disponible' : 'Sans frais' ?>
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="champs-livraison divider" style="padding-top:1.5rem">
                            <label class="field">
                                <span class="field__label">Adresse</span>
                                <input type="text" name="shipping_address_line1" autocomplete="address-line1"
                                       value="<?= e($v('shipping_address_line1')) ?>"
                                       <?= $err('shipping_address_line1') ? 'aria-invalid="true"' : '' ?>>
                                <?php if ($err('shipping_address_line1')): ?>
                                    <span class="field-error"><?= e($err('shipping_address_line1')) ?></span>
                                <?php endif; ?>
                            </label>

                            <label class="field">
                                <span class="field__label">Complément d'adresse <span class="field__optional">(facultatif)</span></span>
                                <span class="field__hint">Bâtiment, étage, code d'accès.</span>
                                <input type="text" name="shipping_address_line2" autocomplete="address-line2"
                                       value="<?= e($v('shipping_address_line2')) ?>">
                            </label>

                            <div class="grid grid--2" style="gap:1.25rem">
                                <label class="field">
                                    <span class="field__label">Code postal</span>
                                    <input type="text" name="shipping_postal_code" inputmode="numeric"
                                           autocomplete="postal-code" maxlength="5"
                                           value="<?= e($v('shipping_postal_code')) ?>"
                                           <?= $err('shipping_postal_code') ? 'aria-invalid="true"' : '' ?>>
                                    <?php if ($err('shipping_postal_code')): ?>
                                        <span class="field-error"><?= e($err('shipping_postal_code')) ?></span>
                                    <?php endif; ?>
                                </label>

                                <label class="field">
                                    <span class="field__label">Ville</span>
                                    <input type="text" name="shipping_city" autocomplete="address-level2"
                                           value="<?= e($v('shipping_city')) ?>"
                                           <?= $err('shipping_city') ? 'aria-invalid="true"' : '' ?>>
                                    <?php if ($err('shipping_city')): ?>
                                        <span class="field-error"><?= e($err('shipping_city')) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>

                            <p class="t-s muted" style="margin-top:1rem">Livraison en France métropolitaine uniquement.</p>
                        </div>

                        <?php if ($points !== []): ?>
                            <div class="champs-retrait divider" style="padding-top:1.5rem">
                                <fieldset style="border:0;margin:0;padding:0">
                                    <legend class="field__label">Où souhaitez-vous retirer votre commande&nbsp;?</legend>
                                    <div class="stack" style="margin-top:.75rem">
                                        <?php foreach ($points as $index => $point): ?>
                                            <label class="choice">
                                                <input type="radio" name="pickup_point_id" value="<?= (int) $point['id'] ?>"
                                                       <?= (string) $point['id'] === $v('pickup_point_id') || ($v('pickup_point_id') === '' && $index === 0) ? 'checked' : '' ?>>
                                                <span>
                                                    <span class="choice__title"><?= e($point['name']) ?></span>
                                                    <span class="choice__detail">
                                                        <?= e($point['address_line1']) ?><br>
                                                        <?= e($point['postal_code']) ?> <?= e($point['city']) ?>
                                                        <?php if (!empty($point['hours'])): ?><br><?= e($point['hours']) ?><?php endif; ?>
                                                    </span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($err('pickup_point_id')): ?>
                                        <p class="field-error" style="margin-top:.5rem"><?= e($err('pickup_point_id')) ?></p>
                                    <?php endif; ?>
                                </fieldset>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section>
                        <h2 class="eyebrow">Votre panier</h2>
                        <div style="margin-top:1rem">
                            <?= View::partial('partials/cart-lines', ['lines' => $cart['lines'], 'editable' => false]) ?>
                        </div>
                        <p class="t-s" style="margin-top:.75rem"><a href="/panier">Modifier le panier</a></p>
                    </section>
                </div>

                <aside>
                    <?= View::partial('partials/cart-summary', [
                        'subtotalCents' => $cart['subtotal_cents'],
                        'shippingCents' => $shippingCents,
                        'actions'       => '<button type="submit" class="btn btn--accent btn--lg btn--block">Payer par carte</button>'
                            . '<p class="t-xs muted" style="margin-top:.75rem">Vous allez être redirigé vers Stripe pour le paiement. '
                            . 'Vos coordonnées bancaires ne transitent pas par nos serveurs.</p>'
                            . '<p class="t-xs muted" style="margin-top:.5rem">En retrait sur place, les frais de port sont retirés '
                            . 'du total au moment du paiement.</p>',
                    ]) ?>
                </aside>
            </form>
        <?php endif; ?>
    </div>
</div>
