<?php
/**
 * Fiche produit.
 *
 * @var array<string, mixed>            $product
 * @var array<int, array<string,mixed>> $related
 * @var array<string, mixed>            $shop
 * @var int                             $photoIndex
 * @var string|null                     $error
 */

use Bouge\Support\Csrf;
use Bouge\Support\Money;
use Bouge\Support\Pricing;
use Bouge\Support\Status;
use Bouge\Support\View;

$price = Pricing::effective($product);
$external = ($product['external_url'] ?? null) !== null && $product['external_url'] !== '';
$seller = $product['external_label'] ?: (parse_url((string) $product['external_url'], PHP_URL_HOST) ?: 'le revendeur');
$images = $product['images'];
$variants = $product['variants'];
$active = $images[$photoIndex] ?? ($images[0] ?? null);

$totalStock = $variants === []
    ? (int) $product['stock']
    : array_sum(array_map(static fn (array $v): int => (int) $v['stock'], $variants));

$flatRate = (int) $shop['shipping']['flat_rate_cents'];
$freeAbove = $shop['shipping']['free_above_cents'];

// Données structurées : elles permettent à Google d'afficher prix et
// disponibilité directement dans les résultats de recherche. Un produit vendu
// par un tiers n'a pas d'offre de notre part — annoncer un prix que nous ne
// maîtrisons pas exposerait à afficher un montant faux.
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => mb_substr(preg_replace('/\s+/', ' ', $product['description']) ?? '', 0, 500),
    'sku'         => (string) $product['id'],
    'brand'       => ['@type' => 'Brand', 'name' => $shop['name']],
    'image'       => array_map(static fn (array $i): string => url($i['url']), $images),
];

if (!$external) {
    $jsonLd['offers'] = [
        '@type'         => 'Offer',
        'url'           => url('/produit/' . $product['slug']),
        'priceCurrency' => 'EUR',
        'price'         => number_format($price->cents / 100, 2, '.', ''),
        'availability'  => $totalStock > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
    ];
}
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

<div class="wrap">
    <div class="section">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb">
                <li><a href="/boutique">Boutique</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="/boutique/<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page"><?= e($product['name']) ?></li>
            </ol>
        </nav>

        <div class="grid grid--split" style="margin-top:2rem;align-items:start">
            <div>
                <div class="gallery__main">
                    <?php if ($active !== null): ?>
                        <img src="<?= e($active['url']) ?>" alt="<?= e($active['alt']) ?>" width="800" height="1000">
                    <?php else: ?>
                        <span class="muted t-s" style="display:grid;place-items:center;height:100%">Photo à venir</span>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <?php /* Les vignettes sont de simples liens : la galerie
                             fonctionne sans JavaScript. */ ?>
                    <ul class="gallery__thumbs">
                        <?php foreach ($images as $index => $image): ?>
                            <li>
                                <a href="?photo=<?= (int) $index ?>"
                                   aria-current="<?= $index === $photoIndex ? 'true' : 'false' ?>">
                                    <span class="sr-only">Voir la photo <?= (int) $index + 1 ?></span>
                                    <img src="<?= e($image['url']) ?>" alt="" loading="lazy" width="200" height="200">
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div>
                <p class="eyebrow"><?= e($product['category_name']) ?></p>
                <h1 class="t-xl" style="margin-top:.5rem"><?= e($product['name']) ?></h1>

                <div style="margin-top:1.5rem">
                    <?php if ($external): ?>
                        <div class="stack-l">
                            <?php if ($price->cents > 0): ?>
                                <?php /* Prix public communiqué par l'éditeur ou la
                                         marque : il situe l'article, mais la vente
                                         se fait chez le revendeur, qui reste maître
                                         de son tarif. */ ?>
                                <div class="row">
                                    <?= View::partial('partials/price', ['price' => $price, 'size' => 'lg']) ?>
                                    <span class="t-s muted">prix éditeur</span>
                                </div>
                            <?php endif; ?>

                            <p class="t-m muted">Vendu par <?= e($seller) ?>.</p>

                            <a class="btn btn--accent btn--lg" href="<?= e($product['external_url']) ?>"
                               target="_blank" rel="noopener noreferrer">
                                Acheter sur <?= e($seller) ?> <span aria-hidden="true">→</span>
                                <span class="sr-only">(nouvel onglet)</span>
                            </a>

                            <?php if (!empty($product['available_in_store'])): ?>
                                <p class="notice">
                                    <strong>Disponible en magasin.</strong>
                                    Passez le voir et repartez avec, sans frais de port ni délai de livraison.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?= View::partial('partials/price', ['price' => $price, 'size' => 'lg']) ?>

                        <?php if ($error !== null): ?>
                            <p class="field-error" role="alert" style="margin-top:1rem"><?= e($error) ?></p>
                        <?php endif; ?>

                        <form method="post" action="/panier/ajouter" class="stack-l" style="margin-top:1.5rem">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                            <?php if ($variants !== []): ?>
                                <?php /* Une seule liste de choix plutôt qu'un
                                         sélecteur par dimension : le formulaire
                                         désigne directement la déclinaison, sans
                                         JavaScript pour faire la correspondance. */ ?>
                                <fieldset class="options">
                                    <legend><?= count($variants) > 0 && $variants[0]['size'] !== null ? 'Taille' : 'Choix' ?></legend>
                                    <div class="options__list">
                                        <?php foreach ($variants as $variant): ?>
                                            <?php
                                            $label = Pricing::variantLabel($variant['size'], $variant['color']) ?? 'Modèle';
                                            $outOfStock = (int) $variant['stock'] <= 0;
                                            ?>
                                            <label class="option">
                                                <input type="radio" name="variant_id" value="<?= (int) $variant['id'] ?>"
                                                       <?= $outOfStock ? 'disabled' : '' ?> required>
                                                <span><?= e($label) ?><?= $outOfStock ? ' — épuisé' : '' ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>

                            <div class="row">
                                <label class="t-s">
                                    <span class="muted">Quantité</span>
                                    <input type="number" name="quantity" value="1" min="1"
                                           max="<?= (int) max($totalStock, 1) ?>"
                                           style="width:5.5rem;text-align:center;margin-left:.5rem;display:inline-block">
                                </label>

                                <button type="submit" class="btn btn--accent btn--lg"
                                        <?= $totalStock <= 0 ? 'disabled' : '' ?>>
                                    <?= $totalStock <= 0 ? 'Rupture de stock' : 'Ajouter au panier' ?>
                                </button>
                            </div>

                            <?php if ($totalStock > 0 && $totalStock <= 5): ?>
                                <p class="t-s" style="color:var(--accent-deep)" role="status">
                                    Plus que <?= (int) $totalStock ?> en stock.
                                </p>
                            <?php endif; ?>
                        </form>

                        <?php if (!empty($product['available_in_store'])): ?>
                            <p class="notice" style="margin-top:1.5rem">
                                <strong>Disponible en magasin.</strong>
                                Passez l'essayer et repartez avec, sans frais de port ni
                                délai de livraison.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="divider" style="margin-top:2.5rem;padding-top:1.5rem">
                    <h2 class="t-s">Description</h2>
                    <?php /* `white-space: pre-line` conserve les retours à la ligne
                             saisis dans l'admin sans autoriser de HTML. */ ?>
                    <p class="muted" style="margin-top:.5rem;white-space:pre-line"><?= e($product['description']) ?></p>
                </div>

                <?php /* Les questions que se pose l'acheteur au moment de
                         décider : quand, comment, et que faire si ça ne va
                         pas. Elles sont répondues ici, pas trois pages plus
                         loin. */ ?>
                <ul class="reassurance">
                    <?php if (!$external): ?>
                        <li>
                            <span aria-hidden="true">→</span>
                            <span><strong>Livraison en France <?= e(Money::format($flatRate)) ?></strong><?php
                                if ($freeAbove !== null): ?>, offerte dès <?= e(Money::format((int) $freeAbove)) ?><?php endif; ?>.
                                Expédition sous 48 h ouvrées.</span>
                        </li>
                        <li>
                            <span aria-hidden="true">→</span>
                            <span><strong>Retrait sans frais</strong> au concept store, dès que la commande est prête.</span>
                        </li>
                        <li>
                            <span aria-hidden="true">→</span>
                            <span><strong>Paiement sécurisé</strong>. Aucune donnée bancaire ne passe par nos serveurs.</span>
                        </li>
                    <?php else: ?>
                        <li>
                            <span aria-hidden="true">→</span>
                            <span><strong>Vendu par <?= e($seller) ?></strong> : livraison, paiement et retours suivent ses conditions.</span>
                        </li>
                        <?php if (!empty($product['available_in_store'])): ?>
                            <li>
                                <span aria-hidden="true">→</span>
                                <span><strong>Au concept store</strong>, à feuilleter et à emporter sans frais de port.</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li>
                        <span aria-hidden="true">→</span>
                        <span>Une hésitation sur la taille ou le modèle ?
                            <a href="mailto:<?= e($shop['email']) ?>">Écrivez-nous</a>, on répond vite.</span>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($related !== []): ?>
            <section class="divider" style="margin-top:5rem;padding-top:3rem">
                <h2 class="t-l">À voir aussi</h2>
                <div style="margin-top:2rem">
                    <?= View::partial('partials/product-grid', ['products' => $related]) ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
