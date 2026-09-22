<?php
/**
 * Mise en page dépouillée : page de connexion uniquement.
 *
 * Pas de navigation ni de pied de page — rien à cliquer tant qu'on n'est pas
 * identifié.
 *
 * @var string               $content
 * @var array<string, mixed> $shop
 * @var string|null          $title
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Administration') . ' — ' . $shop['name']) ?></title>
    <?php /* L'administration ne doit jamais remonter dans les moteurs de recherche. */ ?>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(asset('/assets/brand/icone-512.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/site.css')) ?>">
</head>
<body class="admin admin--blank">
<?= $content ?>
</body>
</html>
