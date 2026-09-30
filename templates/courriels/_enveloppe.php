<?php
/**
 * Enveloppe commune des courriels.
 *
 * Écrite en tableaux et en styles en ligne, à rebours de tout ce qu'on fait
 * ailleurs : les clients de messagerie — Outlook en tête — ignorent les
 * feuilles de style, la grille et les propriétés modernes. C'est laid à lire,
 * mais c'est ce qui s'affiche partout.
 *
 * @var string               $titre
 * @var string               $corps   HTML déjà échappé par le gabarit appelant
 * @var array<string, mixed> $shop
 */
$fond = '#fffbe8';
$encre = '#232323';
$doux = '#59443a';
$trait = '#e6e0ce';
$accent = '#a8441b';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
</head>
<body style="margin:0;padding:0;background:<?= $fond ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:<?= $fond ?>;padding:32px 16px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width:560px;background:#ffffff;border:1px solid <?= $trait ?>;border-radius:10px;">

        <tr><td style="padding:28px 32px 0;">
            <p style="margin:0;font:700 18px/1 Helvetica,Arial,sans-serif;letter-spacing:.04em;color:<?= $encre ?>;">
                <?= e($shop['name_mark'] ?? 'BOUGE') ?>
                <span style="font-weight:600;font-size:12px;letter-spacing:.16em;color:<?= $doux ?>;">
                    <?= e(mb_strtoupper($shop['name_suffix'] ?? '')) ?>
                </span>
            </p>
        </td></tr>

        <tr><td style="padding:24px 32px 32px;font:400 15px/1.6 Helvetica,Arial,sans-serif;color:<?= $encre ?>;">
            <?= $corps ?>
        </td></tr>

        <tr><td style="padding:20px 32px 28px;border-top:1px solid <?= $trait ?>;
                       font:400 12px/1.6 Helvetica,Arial,sans-serif;color:<?= $doux ?>;">
            <?= e($shop['name'] ?? '') ?><?php if (!empty($shop['store']['address'])): ?> — <?= e($shop['store']['address']) ?><?php endif; ?><br>
            <a href="<?= e(url('/')) ?>" style="color:<?= $accent ?>;"><?= e(preg_replace('#^https?://#', '', url('/')) ?: '') ?></a>
            <?php if (!empty($shop['email'])): ?>
                · <a href="mailto:<?= e($shop['email']) ?>" style="color:<?= $accent ?>;"><?= e($shop['email']) ?></a>
            <?php endif; ?>
        </td></tr>

    </table>

</td></tr>
</table>
</body>
</html>
