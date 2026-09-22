<?php
/**
 * @var array<string, mixed> $cart
 */

use Bouge\Support\View;
?>
<div class="wrap">
    <div class="section">
        <h1 class="t-xl">Votre panier</h1>

        <div style="margin-top:2.5rem">
            <?php if ($cart['empty']): ?>
                <?= View::partial('partials/cart-issues', ['issues' => $cart['issues']]) ?>
                <p class="muted" style="margin-top:1.5rem">Votre panier est vide.</p>
                <p style="margin-top:1.5rem"><a class="btn" href="/boutique">Voir le catalogue</a></p>
            <?php else: ?>
                <div class="grid grid--aside">
                    <div>
                        <?php if ($cart['issues'] !== []): ?>
                            <div style="margin-bottom:1.5rem">
                                <?= View::partial('partials/cart-issues', ['issues' => $cart['issues']]) ?>
                            </div>
                        <?php endif; ?>

                        <?= View::partial('partials/cart-lines', ['lines' => $cart['lines'], 'editable' => true]) ?>
                    </div>

                    <aside>
                        <?= View::partial('partials/cart-summary', [
                            'subtotalCents' => $cart['subtotal_cents'],
                            'shippingCents' => null,
                            'actions'       => '<a class="btn btn--accent btn--lg btn--block" href="/commande">Commander</a>'
                                . '<p style="margin-top:.75rem;text-align:center"><a class="link-quiet" href="/boutique">Continuer mes achats</a></p>',
                        ]) ?>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
