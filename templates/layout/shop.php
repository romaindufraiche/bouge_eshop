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
use Bouge\Support\Session;
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

<header class="site-header">
    <div class="wrap">
        <div class="site-header__bar">
            <?php /* Le wordmark de la charte, pas du texte stylé : Sun Motter
                     ne reproduit pas les courbes dessinées du logo. Le mot
                     « Club » lui est adossé dans une autre typographie. */ ?>
            <div class="site-header__logo">
                <?= View::partial('partials/logo', ['shop' => $shop]) ?>
            </div>

            <nav class="site-nav site-nav--desktop" aria-label="Navigation principale">
                <ul>
                    <li><a href="/boutique">Tout le matériel</a></li>
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

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

    <input type="checkbox" id="menu-toggle">
    <nav class="site-nav site-nav--mobile" aria-label="Navigation mobile">
        <div class="wrap">
            <ul>
                <li><a href="/boutique">Tout le matériel</a></li>
                <?php foreach ($categories as $category): ?>
                    <li><a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
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
        <div class="grid grid--4" style="padding:3.5rem 0">
            <div>
                <div class="site-footer__logo">
                    <?= View::partial('partials/logo', ['shop' => $shop, 'ton' => 'creme']) ?>
                </div>
                <p class="t-s" style="margin-top:1rem;opacity:.7"><?= e($shop['tagline']) ?></p>
            </div>

            <nav aria-label="Catégories">
                <h2 class="eyebrow">Catalogue</h2>
                <ul class="t-s">
                    <?php foreach ($categories as $category): ?>
                        <li><a href="/boutique/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <nav aria-label="Informations">
                <h2 class="eyebrow">Informations</h2>
                <ul class="t-s">
                    <li><a href="/livraison">Livraison et retrait</a></li>
                    <li><a href="/cgv">Conditions générales de vente</a></li>
                    <li><a href="/mentions-legales">Mentions légales</a></li>
                </ul>
            </nav>

            <div>
                <h2 class="eyebrow">Contact</h2>
                <ul class="t-s">
                    <li><a href="mailto:<?= e($shop['email']) ?>"><?= e($shop['email']) ?></a></li>
                    <?php if ($shop['phone'] !== ''): ?>
                        <li><?= e($shop['phone']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom">
            <p>© <?= date('Y') ?> <?= e($shop['name']) ?> — Tous droits réservés.</p>
            <p class="note"><?= e($shop['baseline']) ?></p>
        </div>
    </div>
</footer>

</body>
</html>
