<?php
/**
 * Rappel du concept store BOUGE.
 *
 * BOUGE est une salle de sport doublée d'un concept store ; ce site n'en est
 * que le rayon matériel, ouvert à toute heure. Ce bloc explique ce lien — la
 * boutique prolonge le lieu, elle ne le remplace pas — et invite à passer sur
 * place, sans faire semblant d'être un article du catalogue. Il disparaît si
 * la configuration ne nomme aucun lieu, et le bouton n'apparaît que si une
 * adresse web est renseignée.
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
                <p class="eyebrow">Le lieu</p>
                <h2 class="t-l" style="margin-top:.75rem">
                    Derrière <?= e($shop['name']) ?>,<br>
                    il y a <?= e($store['name']) ?>.
                </h2>

                <?php /* Le premier paragraphe est composé plus grand : il porte
                         l'idée, les suivants la développent. La configuration
                         accepte une chaîne comme une liste, pour qui veut s'en
                         tenir à un seul paragraphe. */ ?>
                <?php foreach ((array) ($store['pitch'] ?? []) as $index => $paragraphe): ?>
                    <p class="<?= $index === 0 ? 't-m' : 'muted' ?>"
                       style="margin-top:1rem;max-width:34rem"><?= e($paragraphe) ?></p>
                <?php endforeach; ?>

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
                        Ce qu'on y trouve aussi en rayon
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
