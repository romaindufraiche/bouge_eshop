<?php
/**
 * Page d'accueil.
 *
 * @var array<string, mixed>            $shop
 * @var array<int, array<string,mixed>> $categories
 * @var array<int, array<string,mixed>> $products
 * @var array<int, array<string,mixed>> $vitrine
 * @var array<string, mixed>|null       $highlighted
 */

use Bouge\Support\Money;
use Bouge\Support\Usage;
use Bouge\Support\Pricing;
use Bouge\Support\View;

// Visuels de catégorie : la mascotte de la marque, équipée du rayon qu'elle
// illustre. Chaque accessoire est dessiné par-dessus une illustration de la
// charte (voir bin/habiller-les-mascottes/ pour les tracés) plutôt qu'à côté :
// la grenouille porte son bonnet, elle ne pose pas avec.
//
// Bonnets et maillots partagent la grenouille à la serviette : la charte n'en
// fournit que trois, et l'accessoire suffit à les distinguer.
$categoryImages = [
    'bonnets'     => ['/assets/brand/mascotte-bonnet.png', 'sky'],
    'lunettes'    => ['/assets/brand/mascotte-lunettes.png', 'jade'],
    'accessoires' => ['/assets/brand/mascotte-palmes.png', 'accent'],
    'maillots'    => ['/assets/brand/mascotte-maillot.png', 'sable'],
    // Le livre garde sa vraie couverture : il n'a pas besoin d'illustration.
    'livre'       => ['/assets/images/livre/couverture.jpg', 'couverture'],
];

$flatRate = (int) $shop['shipping']['flat_rate_cents'];
$freeAbove = $shop['shipping']['free_above_cents'];
?>

<?php /* --- Le héros --------------------------------------------------------
         Le site de la salle ouvre sur un bloc anthracite : la vidéo du
         gymnase, le logo crème, une pastille contournée, un tampon qui
         tourne. La boutique ouvrait, elle, sur du crème sur du crème —
         d'où le « trop fade ». Même bloc sombre ici, mêmes signes, avec
         le catalogue qui défile à la place de la vidéo : ce qu'on vend
         reste la première chose qu'on voit.

         Pas de vidéo : elle coûterait deux mégaoctets à chaque visite
         pour une boutique dont le sujet est le produit. */ ?>
<section class="hero">
    <div class="wrap">
        <div class="grid grid--split">
            <div>
                <?php /* La pastille de la salle, aux informations de la
                         boutique. Le premier terme est en orange, comme
                         sur le site : il porte le nom, les suivants le
                         situent. */ ?>
                <ul class="hero__pastille">
                    <li><?= e($shop['name']) ?></li>
                    <li>Courbevoie</li>
                    <li>Expédition sous 48 h</li>
                </ul>

                <?php /* Le titre à l'impératif, comme le nom de la marque :
                         le client fait sa part, la boutique fait la sienne.
                         La coupure est forcée après la première phrase — laissé
                         libre, le titre casse après « le » et laisse un article
                         seul en bout de ligne. D'autres formulations du même
                         registre sont proposées dans le README. */ ?>
                <h1 class="t-xxl" style="margin-top:1.25rem">Nagez.<br>Le matériel suivra.</h1>

                <?php /* La baseline manuscrite sous le titre, à la place
                         qu'occupe « By Melvin Maillot » sur le site de la
                         salle : une signature, pas un surtitre. */ ?>
                <p class="note hero__signature"><?= e($shop['baseline']) ?></p>

                <p class="t-m" style="margin-top:1.25rem;max-width:30rem">
                    Performance, style, confort : bonnets, lunettes, accessoires et
                    maillots, du premier bassin à la ligne d'arrivée. Rien ici
                    qu'on n'utiliserait pas soi-même.
                </p>
                <div class="row" style="margin-top:2rem">
                    <a class="btn btn--accent btn--lg btn--fleche" href="/boutique">Voir le catalogue</a>
                    <a class="btn btn--ghost btn--lg" href="/livraison">Livraison et retrait</a>
                </div>
            </div>

            <div class="hero__vitrine">
            <?php /* Le catalogue qui passe, plutôt qu'une image fixe : ce que
                     vend la boutique se voit dès la première seconde. Deux
                     colonnes qui glissent en sens inverse, sans JavaScript —
                     une animation CSS et un ruban dupliqué pour boucler.

                     Le survol met la bande en pause : sans cela, on ne
                     pourrait pas cliquer sur ce qu'on vient de repérer. */ ?>
            <?php if ($vitrine !== []): ?>
                <?php
                // Deux colonnes de longueur égale, pour qu'aucune ne se vide.
                $moitie = (int) ceil(count($vitrine) / 2);
                $colonnes = [array_slice($vitrine, 0, $moitie), array_slice($vitrine, $moitie)];
                ?>
                <div class="vitrine" aria-label="Aperçu du catalogue">
                    <?php foreach ($colonnes as $index => $colonne): ?>
                        <?php if ($colonne === []) { continue; } ?>
                        <div class="vitrine__colonne<?= $index === 1 ? ' vitrine__colonne--inverse' : '' ?>">
                            <ul class="vitrine__piste">
                                <?php /* Le ruban est écrit deux fois : l'animation le
                                         décale d'exactement sa moitié, et la boucle
                                         est invisible. La copie est masquée aux
                                         lecteurs d'écran et retirée du parcours au
                                         clavier — c'est le même contenu. */ ?>
                                <?php foreach ([false, true] as $copie): ?>
                                    <?php foreach ($colonne as $article): ?>
                                        <li class="vitrine__article"
                                            <?= $copie ? 'aria-hidden="true"' : '' ?>>
                                            <a href="/produit/<?= e($article['slug']) ?>"
                                               <?= $copie ? 'tabindex="-1"' : '' ?>>
                                                <?php /* La photo et le prix, rien d'autre :
                                                         un nom de produit sous chaque vignette
                                                         hacherait la bande, alors qu'un prix
                                                         tient sur une ligne et répond à la
                                                         seule question qu'on se pose en
                                                         regardant passer un catalogue.
                                                         Le nom reste dans le texte de
                                                         remplacement. */ ?>
                                                <img src="<?= e($article['cover']['url']) ?>"
                                                     alt="<?= $copie ? '' : e($article['name']) ?>"
                                                     loading="lazy" width="400" height="400">
                                                <?= View::partial('partials/price', [
                                                    'price' => Pricing::effective($article),
                                                ]) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php /* Catalogue vide : la mascotte reprend sa place plutôt
                         qu'un cadre creux (charte, page 27). */ ?>
                <div style="display:grid;place-items:center;aspect-ratio:1;border-radius:var(--radius-surface);background:var(--sand)">
                    <img src="<?= e(asset('/assets/brand/mascotte-course.png')) ?>"
                         alt="La mascotte de BOUGE, une grenouille en mouvement, serviette sur l'épaule"
                         width="720" height="720" style="width:80%;height:auto">
                </div>
            <?php endif; ?>
                <?php /* Le tampon de la charte, posé en coin comme un
                         cachet de cire. Il tourne lentement : c'est le
                         seul emprunt littéral au site de la salle, et
                         c'est un visuel qui existe déjà.

                         Le tampon anthracite sur une pastille crème, et
                         non le tampon crème : le coin tombe à cheval sur
                         l'anthracite du héros et le sable de la vitrine,
                         et le crème seul s'effaçait sur le second. */ ?>
                <img class="tampon-tournant" src="<?= e(asset('/assets/brand/tampon-anthracite.png')) ?>"
                     alt="" width="560" height="560" loading="lazy">
            </div>
        </div>
    </div>
</section>

<?php /* --- Le ruban ---------------------------------------------------------
         L'orange de la marque en pleine largeur, qui glisse sans fin. Le
         site de la salle en pose un en bas de page ; ici il sert de
         respiration entre le bloc sombre et le crème qui suit, et il
         donne la seule chose qui manquait à l'accueil : du mouvement
         ailleurs que dans la vitrine.

         La liste est écrite deux fois et l'animation décale la piste
         d'exactement sa moitié — même mécanique que la vitrine. La copie
         est masquée aux lecteurs d'écran. */ ?>
<div class="ruban" aria-hidden="true">
    <ul class="ruban__piste">
        <?php foreach ([false, true] as $copie): ?>
            <?php foreach (['Sport et bien plus', 'Nagez. Le matériel suivra.', $shop['name'], 'Courbevoie'] as $mot): ?>
                <li><?= e($mot) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>

<?php if ($highlighted !== null): ?>
    <?php
    $highlightPrice = Pricing::effective($highlighted);
    $highlightExternal = ($highlighted['external_url'] ?? null) !== null && $highlighted['external_url'] !== '';
    $seller = $highlighted['external_label'] ?: (parse_url((string) $highlighted['external_url'], PHP_URL_HOST) ?: 'le revendeur');

    // La description s'ouvre sur une phrase d'accroche, puis le corps du
    // texte : on isole la première ligne pour la composer plus grand, et on
    // ne reprend ici que le premier paragraphe — la fiche porte le reste.
    $lignes = preg_split('/\R/', trim((string) $highlighted['description'])) ?: [];
    $accroche = trim($lignes[0] ?? '');
    $suite = trim(implode("\n", array_slice($lignes, 1)));
    $suite = trim((string) (preg_split('/\n\s*\n/', $suite)[0] ?? ''));

    // Visuels d'intérieur : la couverture est déjà montrée en grand.
    $apercus = array_slice($highlighted['images'] ?? [], 1, 3);
    ?>
    <section class="section section--sand section--line-bottom reveal">
        <div class="wrap">
            <div class="mise-en-avant">
                <?php /* La couverture est montrée entière, jamais recadrée :
                         c'est une image composée, pas une photo de produit. */ ?>
                <div class="mise-en-avant__visuel">
                    <?php if (($highlighted['cover'] ?? null) !== null): ?>
                        <img src="<?= e($highlighted['cover']['url']) ?>"
                             alt="<?= e($highlighted['cover']['alt']) ?>"
                             width="1000" height="1417">
                    <?php endif; ?>
                </div>

                <div class="mise-en-avant__texte">
                    <p class="eyebrow" style="color:var(--accent-deep)">La sélection du moment</p>
                    <h2 class="t-l" style="margin-top:.75rem"><?= e($highlighted['name']) ?></h2>

                    <?php if ($accroche !== ''): ?>
                        <p class="t-m" style="margin-top:1rem"><?= e($accroche) ?></p>
                    <?php endif; ?>

                    <?php if ($suite !== ''): ?>
                        <p class="muted" style="margin-top:1rem"><?= e($suite) ?></p>
                    <?php endif; ?>

                    <div class="row" style="margin-top:1.5rem">
                        <?php if ($highlightExternal && $highlightPrice->cents > 0): ?>
                            <?php /* Prix public de l'éditeur : il situe le livre,
                                     mais ce n'est pas nous qui l'encaissons. */ ?>
                            <?= View::partial('partials/price', ['price' => $highlightPrice, 'size' => 'lg']) ?>
                            <span class="t-s muted">prix éditeur</span>
                        <?php elseif ($highlightExternal): ?>
                            <p class="muted">Vendu sur <?= e($seller) ?></p>
                        <?php else: ?>
                            <?= View::partial('partials/price', ['price' => $highlightPrice, 'size' => 'lg']) ?>
                        <?php endif; ?>

                        <?php if (!empty($highlighted['available_in_store'])): ?>
                            <span class="pill pill--ink">Disponible en magasin</span>
                        <?php endif; ?>
                    </div>

                    <div class="row" style="margin-top:2rem">
                        <?php if ($highlightExternal): ?>
                            <a class="btn btn--accent btn--lg" href="<?= e($highlighted['external_url']) ?>"
                               target="_blank" rel="noopener noreferrer">
                                Acheter sur <?= e($seller) ?> <span aria-hidden="true">&rarr;</span>
                                <span class="sr-only">(nouvel onglet)</span>
                            </a>
                        <?php else: ?>
                            <a class="btn btn--accent btn--lg btn--fleche" href="/produit/<?= e($highlighted['slug']) ?>">Voir le produit</a>
                        <?php endif; ?>
                        <a class="link-quiet" href="/produit/<?= e($highlighted['slug']) ?>">
                            <?= $highlightExternal ? 'Feuilleter et en savoir plus' : 'Toutes les informations' ?>
                        </a>
                    </div>

                    <?php if ($apercus !== []): ?>
                        <?php /* Aperçu de l'intérieur : les doubles pages en disent
                                 plus long qu'un paragraphe de description. */ ?>
                        <ul class="apercus">
                            <?php foreach ($apercus as $index => $image): ?>
                                <li>
                                    <a href="/produit/<?= e($highlighted['slug']) ?>?photo=<?= (int) $index + 1 ?>">
                                        <img src="<?= e($image['url']) ?>" alt="<?= e($image['alt']) ?>"
                                             width="1500" height="1104" loading="lazy">
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* --- Les trois promesses -------------------------------------------
         Mêmes informations qu'avant — livraison, retrait, paiement —,
         mais sur l'aplat vert de la charte et marquées d'un rond, comme
         les trois étapes du site de la salle. Trois lignes de texte gris
         sur crème se lisaient comme une note de bas de page ; cette
         bande-ci se voit.

         Le vert est assombri vers l'encre : le jade de la charte, tel
         quel, ne porte le crème qu'à 3,5:1. À 70 % de jade il atteint
         5,3:1, et la teinte reste reconnaissable. */ ?>
<section class="promesses">
    <div class="wrap">
        <ul class="promesses__liste reveal">
            <li>
                <span class="promesses__rond" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1.5 5h10v8h-10z"/>
                        <path d="M11.5 8h3.4l2.6 2.8V13h-6z"/>
                        <circle cx="5" cy="15" r="1.6"/>
                        <circle cx="14.5" cy="15" r="1.6"/>
                    </svg>
                </span>
                <span>
                    <h3 class="t-s">Livraison en France</h3>
                    <p style="margin-top:.25rem">
                        Expédition sous 48 h ouvrées<?php if ($freeAbove !== null): ?>, offerte dès <?= e(Money::format((int) $freeAbove)) ?><?php endif; ?>.
                    </p>
                </span>
            </li>
            <li>
                <span class="promesses__rond" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 8.5V17h14V8.5"/>
                        <path d="M2 8.5 3.8 3.5h12.4L18 8.5z"/>
                        <path d="M8 17v-4.5h4V17"/>
                    </svg>
                </span>
                <span>
                    <h3 class="t-s">Retrait sur place</h3>
                    <p style="margin-top:.25rem">Sans frais. On vous écrit dès que c'est prêt.</p>
                </span>
            </li>
            <li>
                <span class="promesses__rond" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="8.5" width="13" height="9" rx="1.5"/>
                        <path d="M6.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5"/>
                    </svg>
                </span>
                <span>
                    <h3 class="t-s">Paiement sécurisé</h3>
                    <p style="margin-top:.25rem">
                        Carte bancaire. Aucune donnée de paiement ne passe par nos serveurs.
                    </p>
                </span>
            </li>
        </ul>
    </div>
</section>

<?php /* Le rayon d'abord : c'est l'entrée que tout le monde sait lire, et
         les vignettes illustrées donnent à la page sa couleur. L'entrée par
         l'usage vient juste après, pour qui sait ce qu'il vient faire plutôt
         que ce qu'il vient acheter. */ ?>
<section class="section">
    <div class="wrap">
        <p class="signature">choisissez votre rayon.</p>
        <h2 class="t-l">Par catégorie</h2>
        <?php /* Cinq rayons sur une ligne : à quatre colonnes, le livre
                 retombait seul sur une seconde rangée, ce qui se lit comme un
                 oubli plutôt que comme une grille. */ ?>
        <ul class="grid grid--5 reveal" style="list-style:none;margin:2rem 0 0;padding:0">
            <?php foreach ($categories as $category): ?>
                <?php
                [$visuel, $ton] = $categoryImages[$category['slug']]
                    ?? ['/assets/brand/mascotte-palmes.png', 'sable'];
                ?>
                <li>
                    <a href="/boutique/<?= e($category['slug']) ?>" style="text-decoration:none">
                        <div class="vignette vignette--<?= e($ton) ?>">
                            <?php /* La grenouille est décorative : le nom du rayon
                                     est juste dessous, le répéter en texte de
                                     remplacement ferait doublon à l'oreille. */ ?>
                            <img src="<?= e(asset($visuel)) ?>" alt="" loading="lazy"
                                 width="720" height="720">
                        </div>
                        <h3 class="t-m" style="margin-top:.75rem"><?= e($category['name']) ?></h3>
                        <?php if (!empty($category['description'])): ?>
                            <p class="t-s muted" style="margin-top:.25rem"><?= e($category['description']) ?></p>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php /* L'entrée par l'usage : un nageur sait souvent d'abord ce qu'il vient
         faire, pas dans quel rayon ranger son besoin. C'est l'axe que Speedo
         et Arena mettent en avant. */ ?>
<?php /* Sur le crème, les tuiles blanches se distinguaient à peine du fond :
         elles se lisaient comme des rectangles bordés, pas comme des cartes
         posées. Le sable de la charte les détache. */ ?>
<section class="section section--sand section--line-bottom">
    <div class="wrap">
        <div class="between">
            <div>
                <p class="signature">dites-nous ce que vous venez faire.</p>
                <h2 class="t-l">Vous venez pour quoi ?</h2>
            </div>
            <a class="link-quiet t-s" href="/boutique">Tout le matériel</a>
        </div>

        <ul class="usages reveal">
            <?php foreach (Usage::all() as $slug => $libelle): ?>
                <li>
                    <a href="/usage/<?= e($slug) ?>">
                        <?php /* Neuf zones transparentes, purement décoratives.
                                 Elles ne servent qu'à une chose : avec `:has()`,
                                 la tuile sait laquelle est survolée et penche son
                                 contenu de ce côté. C'est un aimant sans une
                                 ligne de JavaScript — le site n'en charge aucune,
                                 et ce n'est pas un effet de survol qui va
                                 justifier la première. */ ?>
                        <span class="usages__zones" aria-hidden="true">
                            <?php for ($z = 0; $z < 9; $z++): ?><span></span><?php endfor; ?>
                        </span>

                        <span class="usages__contenu">
                            <span class="usages__titre"><?= e($libelle) ?></span>
                            <span class="t-s muted"><?= e(Usage::descriptions()[$slug] ?? '') ?></span>
                            <span class="usages__fleche" aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if ($products !== []): ?>
    <section class="section section--ocean">
        <div class="wrap">
            <div class="between">
                <div>
                    <p class="signature">frais du bassin.</p>
                    <h2 class="t-l">À découvrir</h2>
                </div>
                <a class="t-s" href="/boutique">Tout le catalogue</a>
            </div>
            <div class="reveal" style="margin-top:2rem">
                <?= View::partial('partials/product-grid', ['products' => $products]) ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= View::partial('partials/concept-store', ['shop' => $shop]) ?>

<section class="section--tight section--line">
    <div class="wrap">
        <div class="stack" style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:2rem 0">
            <img class="tourne" src="<?= e(asset('/assets/brand/tampon-anthracite.png')) ?>" alt=""
                 width="560" height="560" style="width:6rem;height:6rem" loading="lazy">
            <p class="t-s muted" style="max-width:28rem">
                Une question sur une taille, un modèle, un délai&nbsp;? Écrivez-nous à
                <a href="mailto:<?= e($shop['email']) ?>"><?= e($shop['email']) ?></a>.
            </p>
        </div>
    </div>
</section>
