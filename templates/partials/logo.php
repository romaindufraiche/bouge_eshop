<?php
/**
 * Verrou typographique de la boutique : le wordmark dessiné de BOUGE, suivi
 * du mot « Club » composé dans une autre typographie.
 *
 * BOUGE est le concept store ; BOUGE Club est sa boutique en ligne. Le logo
 * de la marque n'est pas redessiné : « Club » s'y adosse, en capitales
 * espacées, assez discret pour ne pas concurrencer le wordmark.
 *
 * @var array<string, mixed> $shop
 * @var string|null          $ton    'creme' sur fond anthracite, sinon anthracite
 * @var string|null          $lien   destination, /  par défaut
 */

$ton = $ton ?? 'anthracite';
$lien = $lien ?? '/';
$fichier = $ton === 'creme' ? 'wordmark-creme.png' : 'wordmark-anthracite.png';
?>
<a class="marque marque--<?= e($ton) ?>" href="<?= e($lien) ?>">
    <img class="marque__logo" src="<?= e(asset('/assets/brand/' . $fichier)) ?>"
         alt="" width="720" height="346">
    <span class="marque__club" aria-hidden="true"><?= e($shop['name_suffix']) ?></span>
    <span class="sr-only"><?= e($shop['name']) ?> — accueil</span>
</a>
