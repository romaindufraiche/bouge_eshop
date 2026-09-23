<?php
/**
 * Rappel du concept store BOUGE.
 *
 * Le site vend en ligne, mais la marque est d'abord un lieu : salle de sport
 * et boutique. Ce bloc le dit, sans faire semblant d'être un article du
 * catalogue. Il disparaît si la configuration ne nomme aucun magasin, et le
 * bouton n'apparaît que si une adresse web est renseignée.
 *
 * @var array<string, mixed> $shop
 */

$store = $shop['store'] ?? [];

if (($store['name'] ?? '') === '') {
    return;
}
?>
<section class="section concept-store">
    <div class="wrap">
        <div class="concept-store__grille">
            <div>
                <p class="eyebrow">Avant d'être une boutique</p>
                <h2 class="t-l" style="margin-top:.75rem">
                    <?= e($store['name']) ?>, c'est d'abord un lieu
                </h2>

                <?php if (!empty($store['pitch'])): ?>
                    <p class="t-m" style="margin-top:1rem;max-width:34rem"><?= e($store['pitch']) ?></p>
                <?php endif; ?>

                <ul class="concept-store__infos t-s">
                    <?php if (!empty($store['address'])): ?>
                        <li><span class="muted">Adresse</span><?= e($store['address']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($store['hours'])): ?>
                        <li><span class="muted">Horaires</span><?= e($store['hours']) ?></li>
                    <?php endif; ?>
                </ul>

                <div class="row" style="margin-top:2rem">
                    <?php if (!empty($store['url'])): ?>
                        <a class="btn btn--accent" href="<?= e($store['url']) ?>"
                           target="_blank" rel="noopener">
                            Découvrir la salle <span aria-hidden="true">&rarr;</span>
                            <span class="sr-only">(nouvel onglet)</span>
                        </a>
                    <?php endif; ?>
                    <a class="btn btn--ghost" href="/boutique/selection/en-magasin">
                        Ce qu'on y trouve
                    </a>
                </div>
            </div>

            <?php /* La mascotte plutôt qu'une photo de salle : nous n'en avons
                     pas, et en inventer une serait mentir sur le lieu. */ ?>
            <div class="concept-store__visuel">
                <img src="<?= e(asset('/assets/brand/mascotte-02.png')) ?>"
                     alt="" width="720" height="720" loading="lazy">
            </div>
        </div>
    </div>
</section>
