<?php
/**
 * Avis d'expédition, envoyé dès que le colis part.
 *
 * C'est le message que la boutique n'envoyait pas : le client confirmait sa
 * commande puis n'entendait plus parler de rien jusqu'à l'avis du
 * transporteur — quand il y en avait un.
 *
 * @var array<string, mixed> $commande
 * @var array<string, mixed> $shop
 * @var string               $sujet
 */

use Bouge\Shipping\Tracking;
use Bouge\Support\Status;
use Bouge\Support\View;

$relais = $commande['fulfilment'] === Status::RELAY;
$transporteur = Tracking::nom((string) ($commande['tracking_carrier'] ?? ''));
$numero = trim((string) ($commande['tracking_number'] ?? ''));
$lien = Tracking::lien((string) ($commande['tracking_carrier'] ?? ''), $numero);
$site = rtrim((string) \Bouge\Support\Config::get('site_url', ''), '/');

ob_start();
?>
<h1 style="margin:0 0 4px;font:700 22px/1.25 Helvetica,Arial,sans-serif;color:#232323;">
    <?= $relais ? 'Votre colis est parti.' : 'Votre commande est en route.' ?>
</h1>
<p style="margin:0 0 20px;color:#59443a;">
    Commande <strong style="color:#232323;"><?= e($commande['reference']) ?></strong><?php if ($transporteur !== ''): ?>,
        confiée à <?= e($transporteur) ?><?php endif; ?>.
</p>

<p style="margin:0 0 20px;">
    <?php if ($relais): ?>
        Vous recevrez un avis du transporteur dès que le colis sera arrivé au point
        relais. Pensez à une pièce d'identité pour le retirer.
    <?php else: ?>
        Comptez deux à trois jours ouvrés d'acheminement.
    <?php endif; ?>
</p>

<?php if ($numero !== ''): ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="border-collapse:collapse;margin:0 0 20px;background:#f3eedc;border-radius:8px;">
        <tr>
            <td style="padding:16px 18px;font:400 14px/1.6 Helvetica,Arial,sans-serif;color:#59443a;">
                Numéro de suivi<br>
                <strong style="color:#232323;font-size:16px;letter-spacing:.02em;"><?= e($numero) ?></strong>
            </td>
        </tr>
    </table>

    <?php if ($lien !== null): ?>
        <p style="margin:0 0 24px;">
            <a href="<?= e($lien) ?>"
               style="display:inline-block;padding:12px 22px;border-radius:999px;background:#a8441b;
                      color:#ffffff;text-decoration:none;font:600 14px/1 Helvetica,Arial,sans-serif;">
                Suivre mon colis
            </a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($relais && !empty($commande['relay_name'])): ?>
    <p style="margin:0 0 8px;font:600 12px/1 Helvetica,Arial,sans-serif;letter-spacing:.12em;
              text-transform:uppercase;color:#59443a;">
        Point relais
    </p>
    <p style="margin:0 0 24px;">
        <strong><?= e((string) $commande['relay_name']) ?></strong><br>
        <?= e((string) $commande['relay_address']) ?><br>
        <?= e((string) $commande['relay_postal_code']) ?> <?= e((string) $commande['relay_city']) ?>
    </p>
<?php endif; ?>

<p style="margin:0;font:400 13px/1.6 Helvetica,Arial,sans-serif;color:#59443a;">
    Le détail de la commande reste consultable
    <a href="<?= e($site) ?>/compte/commande/<?= e($commande['reference']) ?>"
       style="color:#a8441b;">dans votre espace</a>.
</p>
<?php
echo View::partial('courriels/_enveloppe', [
    'titre' => $sujet,
    'corps' => (string) ob_get_clean(),
    'shop'  => $shop,
]);
