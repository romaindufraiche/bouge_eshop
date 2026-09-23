<?php
/**
 * Page d'accueil.
 *
 * @var array<string, mixed>            $shop
 * @var array<int, array<string,mixed>> $categories
 * @var array<int, array<string,mixed>> $products
 * @var array<string, mixed>|null       $highlighted
 */

use Bouge\Support\Money;
use Bouge\Support\Usage;
use Bouge\Support\Pricing;
use Bouge\Support\View;

// Visuels de catégorie provisoires, en attente des photos de la marque.
$categoryImages = [
    'bonnets'     => '/assets/images/demo/bonnets.svg',
    'lunettes'    => '/assets/images/demo/lunettes.svg',
    'accessoires' => '/assets/images/demo/accessoires.svg',
    'vetements'   => '/assets/images/demo/vetements.svg',
    // Le livre a sa vraie couverture ; le reste attend les photos de la marque.
    'livre'       => '/assets/images/livre/couverture.jpg',
];

$flatRate = (int) $shop['shipping']['flat_rate_cents'];
$freeAbove = $shop['shipping']['free_above_cents'];
?>

<section class="section section--line-bottom">
    <div class="wrap">
        <div class="grid grid--split">
            <div>
                <p class="note"><?= e($shop['baseline']) ?></p>
                <?php /* Le titre à l'impératif, comme le nom de la marque :
                         le client fait sa part, la boutique fait la sienne.
                         La coupure est forcée après la première phrase — laissé
                         libre, le titre casse après « le » et laisse un article
                         seul en bout de ligne. D'autres formulations du même
                         registre sont proposées dans le README. */ ?>
                <h1 class="t-xxl" style="margin-top:.75rem">Nagez.<br>Le matériel suivra.</h1>
                <p class="t-m muted" style="margin-top:1.5rem;max-width:30rem">
                    Bonnets, lunettes, accessoires et textile. Choisis pour tenir la
                    distance, pas pour faire joli au fond du sac.
                </p>
                <div class="row" style="margin-top:2rem">
                    <a class="btn btn--accent btn--lg" href="/boutique">Voir le catalogue</a>
                    <a class="btn btn--ghost btn--lg" href="/livraison">Livraison et retrait</a>
                </div>
            </div>

            <?php /* La mascotte de la marque : la grenouille, toujours en
                     mouvement, jamais pressée (charte, page 27). */ ?>
            <div style="display:grid;place-items:center;aspect-ratio:1;border-radius:var(--radius-surface);background:var(--sand)">
                <img src="<?= e(asset('/assets/brand/mascotte-course.png')) ?>"
                     alt="La mascotte de BOUGE, une grenouille en mouvement, serviette sur l'épaule"
                     width="720" height="720" style="width:80%;height:auto">
            </div>
        </div>
    </div>
</section>

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
    <section class="section section--sand section--line-bottom">
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
                            <a class="btn btn--accent btn--lg" href="/produit/<?= e($highlighted['slug']) ?>">Voir le produit</a>
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

<section class="section--tight section--line-bottom">
    <div class="wrap">
        <ul class="grid grid--3 t-s" style="list-style:none;margin:0;padding:2.5rem 0">
            <li>
                <h3 class="t-s">Livraison en France</h3>
                <p class="muted" style="margin-top:.25rem">
                    <?= e(Money::format($flatRate)) ?><?php if ($freeAbove !== null): ?>, offerte dès <?= e(Money::format((int) $freeAbove)) ?><?php endif; ?>.
                </p>
            </li>
            <li>
                <h3 class="t-s">Retrait sur place</h3>
                <p class="muted" style="margin-top:.25rem">Sans frais. On vous écrit dès que c'est prêt.</p>
            </li>
            <li>
                <h3 class="t-s">Paiement sécurisé</h3>
                <p class="muted" style="margin-top:.25rem">
                    Carte bancaire via Stripe. Aucune donnée de paiement ne passe par nos serveurs.
                </p>
            </li>
        </ul>
    </div>
</section>

<?php /* Entrée par l'usage, avant l'entrée par le rayon : un nageur sait
         d'abord ce qu'il vient faire, pas dans quelle catégorie ranger son
         besoin. C'est l'axe que Speedo et Arena mettent en avant. */ ?>
<section class="section section--line-bottom">
    <div class="wrap">
        <div class="between">
            <h2 class="t-l">Vous venez pour quoi ?</h2>
            <a class="link-quiet t-s" href="/boutique">Tout le matériel</a>
        </div>

        <ul class="usages">
            <?php foreach (Usage::all() as $slug => $libelle): ?>
                <li>
                    <a href="/usage/<?= e($slug) ?>">
                        <span class="usages__titre"><?= e($libelle) ?></span>
                        <span class="t-s muted"><?= e(Usage::descriptions()[$slug] ?? '') ?></span>
                        <span class="usages__fleche" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <h2 class="t-l">Par catégorie</h2>
        <ul class="grid grid--4" style="list-style:none;margin:2rem 0 0;padding:0">
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="/boutique/<?= e($category['slug']) ?>" style="text-decoration:none">
                        <div style="aspect-ratio:1;overflow:hidden;border-radius:var(--radius-surface);background:var(--sand)">
                            <img src="<?= e($categoryImages[$category['slug']] ?? '/assets/images/demo/accessoires.svg') ?>"
                                 alt="Catégorie <?= e($category['name']) ?>" loading="lazy"
                                 width="800" height="1000" style="width:100%;height:100%;object-fit:cover">
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

<?php if ($products !== []): ?>
    <section class="section section--line">
        <div class="wrap">
            <div class="between">
                <h2 class="t-l">À découvrir</h2>
                <a class="t-s" href="/boutique">Tout le catalogue</a>
            </div>
            <div style="margin-top:2rem">
                <?= View::partial('partials/product-grid', ['products' => $products]) ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?= View::partial('partials/concept-store', ['shop' => $shop]) ?>

<section class="section--tight section--line">
    <div class="wrap">
        <div class="stack" style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:2rem 0">
            <img src="<?= e(asset('/assets/brand/tampon-anthracite.png')) ?>" alt="" width="560" height="560"
                 style="width:6rem;height:6rem" loading="lazy">
            <p class="t-s muted" style="max-width:28rem">
                Une question sur une taille, un modèle, un délai&nbsp;? Écrivez-nous à
                <a href="mailto:<?= e($shop['email']) ?>"><?= e($shop['email']) ?></a>.
            </p>
        </div>
    </div>
</section>
