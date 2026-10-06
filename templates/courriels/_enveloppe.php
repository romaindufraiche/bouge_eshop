<?php
/**
 * Enveloppe commune des courriels.
 *
 * Écrite en tableaux et en styles en ligne, à rebours de tout ce qu'on fait
 * ailleurs : les clients de messagerie — Outlook en tête — ignorent les
 * feuilles de style, la grille et les propriétés modernes. C'est laid à lire,
 * mais c'est ce qui s'affiche partout.
 *
 * Elle reprend l'habillage du site : le bandeau anthracite du héros, le filet
 * d'orange du ruban, le crème de la page, le sable en pied. Un courriel qui
 * arrive dans une boîte doit se reconnaître sans qu'on lise l'expéditeur.
 *
 * Le wordmark est une image, et les images sont bloquées par défaut dans
 * beaucoup de messageries : son texte de remplacement porte donc le nom de la
 * marque, en crème sur l'anthracite. Images coupées, le bandeau dit encore
 * « BOUGE CLUB ».
 *
 * @var string               $titre
 * @var string               $corps   HTML déjà échappé par le gabarit appelant
 * @var array<string, mixed> $shop
 * @var string|null          $apercu  phrase affichée dans la liste des messages
 */
$fond = '#fffbe8';   // crème
$encre = '#232323';  // anthracite
$doux = '#59443a';   // brun café
$trait = '#e6e0ce';
$sable = '#f3eedc';
$accent = '#a8441b'; // orange assombri, celui qui porte du texte
$vif = '#e26129';    // orange vif, pour les aplats sans texte
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?></title>
</head>
<body style="margin:0;padding:0;background:<?= $fond ?>;">

<?php if (!empty($apercu)): ?>
    <?php /* L'extrait que montrent Gmail et Mail à côté de l'objet. Sans lui,
             ils affichent les premiers mots du corps — souvent « Commande
             BC-2026-0007 du 12/03/2026 ». Masqué dans le message lui-même. */ ?>
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0;">
        <?= e($apercu) ?>
    </div>
<?php endif; ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       bgcolor="<?= $fond ?>" style="background:<?= $fond ?>;padding:32px 16px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width:560px;background:#ffffff;border:1px solid <?= $trait ?>;border-radius:12px;overflow:hidden;">

        <?php /* Le bandeau anthracite, comme le héros de l'accueil. */ ?>
        <tr><td bgcolor="<?= $encre ?>" style="background:<?= $encre ?>;padding:22px 32px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="vertical-align:middle;">
                    <?php /* La couleur et la graisse sont posées sur l'image
                             elle-même : quand la messagerie bloque les images,
                             c'est le texte de remplacement qui s'affiche à sa
                             place, et il hérite de ce style. Sans cela il
                             sortait en noir sur l'anthracite, donc invisible. */ ?>
                    <img src="<?= e(url('/assets/brand/wordmark-creme.png')) ?>"
                         alt="<?= e($shop['name_mark'] ?? 'BOUGE') ?>" width="92" height="44"
                         style="display:block;width:92px;height:auto;border:0;
                                color:<?= $fond ?>;font:700 20px/1.2 Helvetica,Arial,sans-serif;
                                letter-spacing:.04em;">
                </td>
                <td style="vertical-align:middle;padding-left:12px;
                           font:600 12px/1 Helvetica,Arial,sans-serif;letter-spacing:.18em;color:<?= $fond ?>;">
                    <?= e(mb_strtoupper((string) ($shop['name_suffix'] ?? ''))) ?>
                </td>
            </tr></table>
        </td></tr>

        <?php /* Le filet d'orange : le ruban de l'accueil, réduit à sa plus
                 simple expression. Quatre pixels suffisent à faire le lien. */ ?>
        <tr><td bgcolor="<?= $vif ?>" style="background:<?= $vif ?>;height:4px;line-height:4px;font-size:0;">&nbsp;</td></tr>

        <tr><td style="padding:28px 32px 32px;font:400 15px/1.6 Helvetica,Arial,sans-serif;color:<?= $encre ?>;">
            <?= $corps ?>
        </td></tr>

        <tr><td bgcolor="<?= $sable ?>" style="background:<?= $sable ?>;padding:20px 32px;border-top:1px solid <?= $trait ?>;
                       font:400 12px/1.6 Helvetica,Arial,sans-serif;color:<?= $doux ?>;">
            <strong style="color:<?= $encre ?>;"><?= e($shop['name'] ?? '') ?></strong><?php if (!empty($shop['store']['address'])): ?> — <?= e($shop['store']['address']) ?><?php endif; ?><br>
            <a href="<?= e(url('/')) ?>" style="color:<?= $accent ?>;"><?= e(preg_replace('#^https?://#', '', url('/')) ?: '') ?></a>
            <?php if (!empty($shop['email'])): ?>
                · <a href="mailto:<?= e($shop['email']) ?>" style="color:<?= $accent ?>;"><?= e($shop['email']) ?></a>
            <?php endif; ?>
        </td></tr>

    </table>

    <?php /* La baseline de la marque sous la carte, comme en pied de site. */ ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
        <tr><td align="center" style="padding:16px 8px 0;font:400 11px/1.5 Helvetica,Arial,sans-serif;color:<?= $doux ?>;">
            <?= e((string) ($shop['baseline'] ?? '')) ?>
        </td></tr>
    </table>

</td></tr>
</table>
</body>
</html>
