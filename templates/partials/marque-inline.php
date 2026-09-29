<?php
/**
 * Le nom de la marque composé dans sa propre typographie, au fil d'un texte.
 *
 * Le wordmark de BOUGE est un dessin, pas une police : on ne peut donc pas
 * « écrire BOUGE » dans un titre, il faut y poser l'image. Ce gabarit le fait
 * en la calant sur la ligne de base du texte qui l'entoure, et en laissant le
 * nom lisible aux lecteurs d'écran.
 *
 * @var array<string, mixed> $shop
 * @var bool|null            $club  true pour « BOUGE Club », false pour « BOUGE »
 * @var string|null          $ton   'creme' sur fond anthracite, sinon anthracite
 */

$club = $club ?? false;
$ton = $ton ?? 'anthracite';
$fichier = $ton === 'creme' ? 'wordmark-creme.png' : 'wordmark-anthracite.png';
?>
<span class="marque-inline marque-inline--<?= e($ton) ?>">
    <img src="<?= e(asset('/assets/brand/' . $fichier)) ?>" alt="" width="720" height="346">
    <?php if ($club): ?>
        <span class="marque-inline__club" aria-hidden="true"><?= e($shop['name_suffix']) ?></span>
    <?php endif; ?>
    <span class="sr-only"><?= e($club ? $shop['name'] : $shop['name_mark']) ?></span>
</span>
