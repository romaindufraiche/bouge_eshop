<?php
/**
 * Mise en page de l'administration.
 *
 * Barre latérale fixe sur grand écran, navigation empilée sur mobile. Les
 * libellés sont ceux du métier (« Produits », « Commandes »), jamais du
 * vocabulaire technique.
 *
 * @var string               $content
 * @var array<string, mixed> $shop
 * @var string|null          $title
 */

use Bouge\Support\Auth;
use Bouge\Support\Csrf;
use Bouge\Support\Session;

$user = Auth::user();
$flash = Session::takeFlash('admin');

// Onglet courant : comparé au début du chemin pour que
// /admin/produits/12 garde « Produits » souligné.
$path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), '?') ?: '/admin';

$sections = [
    '/admin'            => 'Tableau de bord',
    '/admin/produits'   => 'Produits',
    '/admin/categories' => 'Catégories',
    '/admin/points-de-retrait' => 'Points de retrait',
    '/admin/commandes'  => 'Commandes',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Administration') . ' — ' . $shop['name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(asset('/assets/brand/icone-512.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/site.css')) ?>">
</head>
<body class="admin">

<a class="sr-only skip-link" href="#contenu">Aller au contenu</a>

<div class="admin-shell">
    <aside class="admin-side">
        <a class="admin-side__logo" href="/admin">
            <img src="<?= e(asset('/assets/brand/wordmark-creme.png')) ?>"
                 alt="<?= e($shop['name']) ?>" width="720" height="346">
            <span class="eyebrow">Administration</span>
        </a>

        <nav class="admin-nav" aria-label="Sections de l'administration">
            <ul>
                <?php foreach ($sections as $href => $label): ?>
                    <?php
                    // « /admin » ne doit correspondre qu'à lui-même, sinon il
                    // serait actif sur toutes les pages.
                    $active = $href === '/admin'
                        ? $path === '/admin' || $path === '/admin/'
                        : str_starts_with($path, $href);
                    ?>
                    <li>
                        <a href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                            <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="admin-side__foot">
            <p class="t-xs"><?= e($user['name'] ?? $user['email'] ?? '') ?></p>
            <p class="t-xs"><a href="/" target="_blank" rel="noopener">Voir la boutique</a></p>
            <form method="post" action="/admin/deconnexion">
                <?= Csrf::field() ?>
                <button class="btn btn--ghost btn--sm" type="submit">Se déconnecter</button>
            </form>
        </div>
    </aside>

    <main class="admin-main" id="contenu">
        <?php if ($flash !== null): ?>
            <p class="notice" role="status"><?= e($flash) ?></p>
        <?php endif; ?>

        <?= $content /* déjà échappé par le gabarit de la page */ ?>
    </main>
</div>

</body>
</html>
