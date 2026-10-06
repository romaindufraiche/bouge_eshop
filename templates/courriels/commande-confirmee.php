<?php
/**
 * Accusé de commande, envoyé dès que le paiement est confirmé.
 *
 * @var array<string, mixed> $commande
 * @var array<string, mixed> $shop
 * @var string               $sujet
 */

use Bouge\Support\Money;
use Bouge\Support\Status;
use Bouge\Support\View;

$retrait = $commande['fulfilment'] === Status::PICKUP;
$relais = $commande['fulfilment'] === Status::RELAY;

ob_start();
?>
<h1 style="margin:0 0 4px;font:700 22px/1.25 Helvetica,Arial,sans-serif;color:#232323;">
    Merci, c'est confirmé.
</h1>
<p style="margin:0 0 20px;color:#59443a;">
    Commande <strong style="color:#232323;"><?= e($commande['reference']) ?></strong>
    du <?= e(date('d/m/Y', strtotime((string) $commande['created_at']))) ?>.
</p>

<p style="margin:0 0 20px;">
    <?php if ($retrait): ?>
        Nous vous prévenons dès qu'elle est prête à être retirée.
    <?php elseif ($relais): ?>
        Votre colis part sous 48 heures ouvrées&nbsp;; le transporteur vous préviendra
        dès qu'il sera arrivé au point relais.
    <?php else: ?>
        Votre commande part sous 48 heures ouvrées.
    <?php endif; ?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-collapse:collapse;margin:0 0 20px;">
    <?php foreach ($commande['items'] as $ligne): ?>
        <tr>
            <td style="padding:8px 0;border-bottom:1px solid #e6e0ce;font:400 14px/1.5 Helvetica,Arial,sans-serif;">
                <?= e($ligne['product_name']) ?>
                <?php if (!empty($ligne['variant_label'])): ?>
                    <span style="color:#59443a;">— <?= e($ligne['variant_label']) ?></span>
                <?php endif; ?>
                <span style="color:#59443a;">× <?= (int) $ligne['quantity'] ?></span>
            </td>
            <td align="right" style="padding:8px 0;border-bottom:1px solid #e6e0ce;white-space:nowrap;
                                     font:400 14px/1.5 Helvetica,Arial,sans-serif;">
                <?= e(Money::format((int) $ligne['line_total_cents'])) ?>
            </td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <td style="padding:8px 0;font:400 14px/1.5 Helvetica,Arial,sans-serif;color:#59443a;">
            <?= e(Status::fulfilments()[$commande['fulfilment']] ?? 'Livraison') ?>
        </td>
        <td align="right" style="padding:8px 0;white-space:nowrap;
                                 font:400 14px/1.5 Helvetica,Arial,sans-serif;color:#59443a;">
            <?= (int) $commande['shipping_cents'] === 0 ? 'Offerte' : e(Money::format((int) $commande['shipping_cents'])) ?>
        </td>
    </tr>
    <tr>
        <td style="padding:10px 0 0;border-top:2px solid #232323;
                   font:700 16px/1.5 Helvetica,Arial,sans-serif;">Total</td>
        <?php /* `nowrap` : à 16 px, « 133,88 € » débordait de sa colonne et
                 le symbole euro tombait seul à la ligne suivante. Les lignes
                 d'articles le portaient déjà ; le total l'avait oublié. */ ?>
        <td align="right" style="padding:10px 0 0;border-top:2px solid #232323;white-space:nowrap;
                                 font:700 16px/1.5 Helvetica,Arial,sans-serif;">
            <?= e(Money::format((int) $commande['total_cents'])) ?>
        </td>
    </tr>
</table>

<p style="margin:0 0 6px;font:700 13px/1.5 Helvetica,Arial,sans-serif;letter-spacing:.08em;
          text-transform:uppercase;color:#59443a;">
    <?= $retrait ? 'Retrait' : 'Livraison' ?>
</p>
<p style="margin:0 0 24px;color:#232323;">
    <?php if ($relais): ?>
        <?= e((string) $commande['relay_name']) ?><br>
        <?= e((string) $commande['relay_address']) ?><br>
        <?= e((string) $commande['relay_postal_code']) ?> <?= e((string) $commande['relay_city']) ?><br>
        <span style="color:#59443a;">Pensez à une pièce d'identité pour le retirer.</span>
    <?php elseif ($retrait): ?>
        <?= e((string) ($commande['pickup_name'] ?? '')) ?><br>
        <?= e((string) ($commande['pickup_address'] ?? '')) ?><br>
        <?= e((string) ($commande['pickup_postal_code'] ?? '')) ?> <?= e((string) ($commande['pickup_city'] ?? '')) ?>
    <?php else: ?>
        <?= e((string) $commande['customer_name']) ?><br>
        <?= e((string) $commande['shipping_address_line1']) ?><br>
        <?php if (!empty($commande['shipping_address_line2'])): ?>
            <?= e((string) $commande['shipping_address_line2']) ?><br>
        <?php endif; ?>
        <?= e((string) $commande['shipping_postal_code']) ?> <?= e((string) $commande['shipping_city']) ?>
    <?php endif; ?>
</p>

<?php
// Le lien de suivi.
// Avec un compte, il mène à l'espace client ; sans compte, au suivi ouvert
// par le jeton — la boutique autorise la commande sans compte, le lien doit
// donc s'ouvrir sans compte lui aussi.
$suivi = $commande['customer_id'] !== null || empty($commande['tracking_token'])
    ? url('/compte/commande/' . rawurlencode((string) $commande['reference']))
    : url(\Bouge\Controller\CheckoutController::trackingPath(
        (string) $commande['reference'],
        (string) $commande['tracking_token']
    ));
?>

<p style="margin:0 0 20px;">
    <a href="<?= e($suivi) ?>"
       style="display:inline-block;padding:12px 22px;border-radius:999px;background:#a8441b;
              color:#ffffff;text-decoration:none;font:600 14px/1 Helvetica,Arial,sans-serif;">
        Suivre ma commande
    </a>
</p>

<?php if (!$retrait): ?>
    <?php /* Dire tout de suite que le numéro de suivi viendra plus tard évite
             le courriel « où est mon colis ? » du lendemain. Inutile sur un
             retrait : le premier paragraphe l'a déjà dit. */ ?>
    <p style="margin:0;font:400 13px/1.6 Helvetica,Arial,sans-serif;color:#59443a;">
        Le numéro de suivi du transporteur apparaîtra sur cette page dès le départ
        du colis, et vous recevrez un message à ce moment-là.
    </p>
<?php endif; ?>
<?php
$corps = (string) ob_get_clean();

echo View::partial('courriels/_enveloppe', [
    'titre' => $sujet,
    'corps' => $corps,
    'shop'  => $shop,
]);
