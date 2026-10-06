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

use Bouge\Support\View;

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
                <?php /* Les deux noms sont composés dans leur propre
                         typographie : le wordmark est un dessin, pas une
                         police, et l'écrire en capitales ordinaires reviendrait
                         à citer la marque sans la montrer. */ ?>
                <h2 class="t-l" style="margin-top:.75rem">
                    Derrière <?= View::partial('partials/marque-inline', [
                        'shop' => $shop, 'club' => true, 'ton' => 'creme',
                    ]) ?>,<br>
                    <?php /* Pas de point après le second nom : le wordmark en
                             porte un, et l'ajouter en donnerait deux. */ ?>
                    il y a <?= View::partial('partials/marque-inline', [
                        'shop' => $shop, 'club' => false, 'ton' => 'creme',
                    ]) ?>
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

            <div>
                <?php /* Le site de la salle, en capture : c'est le lieu
                         lui-même qui se montre, avec ses vraies images et
                         son vrai monde. La mascotte occupait cette place
                         faute de mieux — elle illustrait la marque, pas
                         l'endroit dont parle le paragraphe à côté.

                         La capture est cliquable quand l'adresse du site
                         est renseignée : on voit la salle, on y va. */ ?>
                <?php
                $capture = '<img src="' . e(asset('/assets/images/salle/site-bouge.jpg')) . '"'
                    . ' alt="La page d\'accueil du site de ' . e($store['name'])
                    . ' : le logo de la marque sur une photo de la salle."'
                    . ' width="1600" height="806" loading="lazy">';
                ?>
                <div class="concept-store__visuel concept-store__visuel--capture">
                    <?php if (!empty($store['url'])): ?>
                        <a href="<?= e($store['url']) ?>" target="_blank" rel="noopener">
                            <?= $capture ?>
                            <span class="sr-only">(nouvel onglet)</span>
                        </a>
                    <?php else: ?>
                        <?= $capture ?>
                    <?php endif; ?>
                </div>

                <?php
                $plan = $store['map'] ?? [];
                $lat = (string) ($plan['lat'] ?? '');
                $lon = (string) ($plan['lon'] ?? '');
                ?>
                <?php if ($lat !== '' && $lon !== ''): ?>
                    <?php
                    // OpenStreetMap attend un cadrage, pas un niveau de zoom :
                    // on fabrique la fenêtre autour du point.
                    //
                    // À un niveau de zoom z, un pixel couvre
                    // 156543 · cos(latitude) / 2^z mètres, et un degré de
                    // longitude vaut 111320 · cos(latitude) mètres. Le cosinus
                    // se simplifie : la largeur en degrés ne dépend que de la
                    // largeur du cadre en pixels et du zoom.
                    //
                    // 480 px est la largeur retenue pour le cadre — la colonne
                    // fait 445 px sur grand écran et un peu moins sur
                    // téléphone. Une estimation un peu large vaut mieux qu'un
                    // plan trop serré sur le marqueur.
                    $zoom = max(10, min(19, (int) ($plan['zoom'] ?? 17)));
                    $largeur = 480 * 1.40625 / (2 ** $zoom) / 2;

                    // Le cadre est au format 4/3, et un degré de latitude est
                    // plus « long » qu'un degré de longitude sous nos
                    // latitudes : d'où le cosinus, qui ne se simplifie pas ici.
                    $hauteur = $largeur * 0.75 * cos(deg2rad((float) $lat));

                    $cadre = implode(',', [
                        round((float) $lon - $largeur, 6),
                        round((float) $lat - $hauteur, 6),
                        round((float) $lon + $largeur, 6),
                        round((float) $lat + $hauteur, 6),
                    ]);

                    // Pas de paramètre « marker » : l'épingle d'OpenStreetMap
                    // est remplacée par le monogramme de la marque, posé
                    // au-dessus du cadre.
                    $embarque = 'https://www.openstreetmap.org/export/embed.html?'
                        . http_build_query([
                            'bbox'  => $cadre,
                            'layer' => 'mapnik',
                        ]);

                    $grand = 'https://www.openstreetmap.org/?'
                        . http_build_query(['mlat' => $lat, 'mlon' => $lon, 'zoom' => $zoom]);
                    ?>
                    <?php /* Un plan d'OpenStreetMap plutôt que de Google :
                             celui-ci se déplace et se zoome sans qu'on ait de
                             JavaScript à charger, sans compte à ouvrir, et
                             sans mouchard — donc sans bannière de
                             consentement à imposer au visiteur.

                             Chargé en différé : il ne part qu'une fois la
                             section atteinte, et une page d'accueil n'a pas à
                             appeler un service tiers avant d'être lue. */ ?>
                    <div class="concept-store__plan">
                        <iframe src="<?= e($embarque) ?>" loading="lazy"
                                title="Plan de <?= e($store['name']) ?>, <?= e($store['address'] ?? '') ?>"
                                referrerpolicy="no-referrer"></iframe>

                        <?php /* Le monogramme de la marque plutôt que l'épingle
                                 d'OpenStreetMap. Le cadrage est centré sur
                                 l'adresse, donc le repère tombe pile dessus au
                                 chargement.

                                 Il ne capte pas la souris : on peut déplacer et
                                 zoomer la carte au travers. En revanche il reste
                                 au centre du cadre — c'est un repère, pas une
                                 épingle accrochée au terrain. */ ?>
                        <img class="concept-store__repere"
                             src="<?= e(asset('/assets/brand/monogramme-orange.png')) ?>"
                             alt="" width="512" height="512" loading="lazy">
                    </div>
                    <p class="t-xs" style="margin-top:.75rem">
                        <a href="<?= e($grand) ?>" target="_blank" rel="noopener">
                            Voir le plan en grand <span aria-hidden="true">&rarr;</span>
                            <span class="sr-only">(nouvel onglet)</span>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
