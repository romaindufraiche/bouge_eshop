<?php
/**
 * Mise en page des pages publiques.
 *
 * @var string                     $content     corps de la page, déjà rendu
 * @var array<string, mixed>       $shop        config/shop.php
 * @var string|null                $title       titre court de la page
 * @var string|null                $description meta description
 * @var string|null                $canonical   chemin canonique
 * @var bool|null                  $noindex
 */

use Bouge\Repository\CategoryRepository;
use Bouge\Support\Cart;
use Bouge\Support\Money;
use Bouge\Support\Session;
use Bouge\Support\Usage;
use Bouge\Support\View;

$categories = (new CategoryRepository())->all();
$cartCount = Cart::count();
$pageTitle = isset($title) && $title !== ''
    ? $title . ' — ' . $shop['name']
    : $shop['name'] . ' — ' . $shop['tagline'];
$flash = Session::takeFlash('shop');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <?php if (!empty($description)): ?>
        <meta name="description" content="<?= e($description) ?>">
    <?php endif; ?>
    <?php if (!empty($noindex)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= e(url($canonical)) ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="<?= e($shop['name']) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <?php if (!empty($description)): ?>
        <meta property="og:description" content="<?= e($description) ?>">
    <?php endif; ?>

    <link rel="icon" href="<?= e(asset('/assets/brand/icone-512.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('/assets/brand/icone-apple.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/site.css')) ?>">
</head>
<body class="boutique">

<a class="sr-only skip-link" href="#contenu">Aller au contenu</a>

<?php /* --- En-tête -----------------------------------------------------------
     Trois étages, comme sur les sites des marques de natation : un bandeau
     de réassurance, la barre principale (logo, recherche, panier), puis la
     navigation. Le menu déroulant s'ouvre au survol ET au focus clavier, le
     menu mobile par une case à cocher : rien ici n'a besoin de JavaScript. */ ?>
<div class="bandeau">
    <div class="wrap">
        <ul>
            <li>Livraison <?= e(Money::format((int) $shop['shipping']['flat_rate_cents'])) ?> en France<?php
                if ($shop['shipping']['free_above_cents'] !== null): ?>, offerte dès <?= e(Money::format((int) $shop['shipping']['free_above_cents'])) ?><?php endif; ?></li>
            <li>Retrait sans frais au concept store</li>
            <li>Paiement sécurisé par Stripe</li>
        </ul>
    </div>
</div>

<header class="site-header">
    <div class="wrap">
        <div class="site-header__bar">
            <?php /* Le wordmark de la charte, pas du texte stylé : Sun Motter
                     ne reproduit pas les courbes dessinées du logo. Le mot
                     « Club » lui est adossé dans une autre typographie. */ ?>
            <div class="site-header__logo">
                <?= View::partial('partials/logo', ['shop' => $shop]) ?>
            </div>

            <form class="recherche" method="get" action="/recherche" role="search">
                <label class="sr-only" for="recherche">Rechercher un produit</label>
                <input type="search" id="recherche" name="q" placeholder="Rechercher un produit"
                       value="<?= e((string) ($_GET['q'] ?? '')) ?>">
                <button type="submit">
                    <span class="sr-only">Rechercher</span>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor"
                         stroke-width="1.6" aria-hidden="true">
                        <circle cx="8" cy="8" r="5.5"/>
                        <line x1="12" y1="12" x2="16.5" y2="16.5"/>
                    </svg>
                </button>
            </form>

            <div class="row">
                <a class="cart-link" href="/panier">
                    Panier
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count nums"><?= (int) $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php /* Le menu mobile est piloté par une case à cocher : il
                         fonctionne sans JavaScript. */ ?>
                <label class="burger" for="menu-toggle">
                    <span class="sr-only">Ouvrir le menu</span>
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                         stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <line x1="3" y1="6" x2="19" y2="6"/>
                        <line x1="3" y1="11" x2="19" y2="11"/>
                        <line x1="3" y1="16" x2="19" y2="16"/>
                    </svg>
                </label>
            </div>
        </div>
    </div>

    <nav class="site-nav site-nav--desktop" aria-label="Navigation principale">
        <div class="wrap">
            <ul>
                <li class="menu">
                    <a href="/boutique" class="menu__entree">
                        Tout le matériel
                        <span class="menu__fleche" aria-hidden="true">▾</span>
                    </a>

                    <?php /* Le panneau reprend les colonnes des sites de
                             natation : le type de produit, l'usage, les
                             raccourcis. Il reste dans le flux du document,
                             donc accessible au clavier et lisible sans CSS. */ ?>
                    <div class="menu__panneau">
                        <div class="wrap menu__colonnes">
                            <div>
                                <p class="eyebrow">Par catégorie</p>
                                <ul>
                                    <?php foreach ($categories as $category): ?>
                                        <li><a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a></li>
                                    <?php endforeach; ?>
                                    <li><a href="/boutique"><strong>Tout voir</strong></a></li>
                                </ul>
                            </div>

                            <div>
                                <p class="eyebrow">Par usage</p>
                                <ul>
                                    <?php foreach (Usage::all() as $slug => $libelle): ?>
                                        <li><a href="/usage/<?= e($slug) ?>"><?= e($libelle) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div>
                                <p class="eyebrow">Raccourcis</p>
                                <ul>
                                    <li><a href="/boutique/selection/nouveautes">Nouveautés</a></li>
                                    <li><a href="/boutique/selection/promotions">Promotions</a></li>
                                    <li><a href="/boutique/selection/en-magasin">Disponible en magasin</a></li>
                                    <li><a href="/livraison">Livraison et retrait</a></li>
                                </ul>
                            </div>

                            <?php /* Un rappel visuel du livre : c'est le produit
                                     que la marque met en avant. */ ?>
                            <div class="menu__mise-en-avant">
                                <p class="eyebrow">Le livre</p>
                                <a href="/produit/corps-et-esprit">
                                    <img src="<?= e(asset('/assets/images/livre/couverture.jpg')) ?>"
                                         alt="" width="1000" height="1417" loading="lazy">
                                    <span>Corps et esprit
                                        <span class="muted t-xs d-block">Ce que la natation m'a appris sur la vie</span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>

                <?php /* Les trois usages les plus courants sont sortis du menu :
                         c'est la question que se pose le nageur en arrivant. */ ?>
                <li><a href="/usage/<?= e(Usage::TRAINING) ?>"><?= e(Usage::label(Usage::TRAINING)) ?></a></li>
                <li><a href="/usage/<?= e(Usage::COMPETITION) ?>"><?= e(Usage::label(Usage::COMPETITION)) ?></a></li>
                <li><a href="/usage/<?= e(Usage::LEISURE) ?>"><?= e(Usage::label(Usage::LEISURE)) ?></a></li>
                <li><a href="/boutique/selection/promotions" class="lien-promo">Promotions</a></li>
                <li><a href="/produit/corps-et-esprit">Le livre</a></li>
            </ul>
        </div>
    </nav>

    <input type="checkbox" id="menu-toggle">
    <nav class="site-nav site-nav--mobile" aria-label="Navigation mobile">
        <div class="wrap">
            <?php /* Sur mobile, chaque groupe est un bloc dépliable : la liste
                     complète tiendrait sur trois écrans. */ ?>
            <details open>
                <summary>Par catégorie</summary>
                <ul>
                    <li><a href="/boutique">Tout le matériel</a></li>
                    <?php foreach ($categories as $category): ?>
                        <li><a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <details>
                <summary>Par usage</summary>
                <ul>
                    <?php foreach (Usage::all() as $slug => $libelle): ?>
                        <li><a href="/usage/<?= e($slug) ?>"><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <details>
                <summary>Raccourcis</summary>
                <ul>
                    <li><a href="/boutique/selection/nouveautes">Nouveautés</a></li>
                    <li><a href="/boutique/selection/promotions">Promotions</a></li>
                    <li><a href="/boutique/selection/en-magasin">Disponible en magasin</a></li>
                    <li><a href="/produit/corps-et-esprit">Le livre</a></li>
                    <li><a href="/livraison">Livraison et retrait</a></li>
                </ul>
            </details>
        </div>
    </nav>
</header>

<main id="contenu">
    <?php if ($flash !== null): ?>
        <div class="wrap" style="padding-top:1.5rem">
            <p class="notice" role="status"><?= e($flash) ?></p>
        </div>
    <?php endif; ?>

    <?= $content /* déjà échappé par le gabarit de la page */ ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <?php /* Quatre colonnes comme chez les marques de natation : ce qu'on
                 vend, comment s'y retrouver, les réponses aux questions, et
                 comment nous joindre. */ ?>
        <div class="grid grid--4" style="padding:3.5rem 0">
            <div>
                <div class="site-footer__logo">
                    <?= View::partial('partials/logo', ['shop' => $shop, 'ton' => 'creme']) ?>
                </div>
                <p class="t-s" style="margin-top:1rem;opacity:.7">
                    La boutique en ligne du concept store BOUGE.<br><?= e($shop['tagline']) ?>.
                </p>
            </div>

            <nav aria-label="Boutique">
                <h2 class="eyebrow">Boutique</h2>
                <ul class="t-s">
                    <li><a href="/boutique">Tout le matériel</a></li>
                    <?php foreach ($categories as $category): ?>
                        <li><a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <nav aria-label="Par usage">
                <h2 class="eyebrow">Par usage</h2>
                <ul class="t-s">
                    <?php foreach (Usage::all() as $slug => $libelle): ?>
                        <li><a href="/usage/<?= e($slug) ?>"><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="/boutique/selection/promotions">Promotions</a></li>
                </ul>
            </nav>

            <nav aria-label="Aide et informations">
                <h2 class="eyebrow">Aide</h2>
                <ul class="t-s">
                    <li><a href="/livraison">Livraison et retrait</a></li>
                    <li><a href="/boutique/selection/en-magasin">Disponible en magasin</a></li>
                    <li><a href="mailto:<?= e($shop['email']) ?>">Nous écrire</a></li>
                    <li><a href="/cgv">Conditions générales de vente</a></li>
                    <li><a href="/mentions-legales">Mentions légales</a></li>
                </ul>
                <?php if ($shop['phone'] !== ''): ?>
                    <p class="t-s" style="margin-top:.75rem;opacity:.7"><?= e($shop['phone']) ?></p>
                <?php endif; ?>
            </nav>
        </div>

        <div class="site-footer__bottom">
            <p>© <?= date('Y') ?> <?= e($shop['name']) ?> — Tous droits réservés.</p>
            <p class="note"><?= e($shop['baseline']) ?></p>
        </div>
    </div>
</footer>

</body>
</html>
