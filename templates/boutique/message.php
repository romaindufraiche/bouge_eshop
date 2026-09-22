<?php
/**
 * Page de message simple : 404, erreur, confirmation sans données.
 *
 * @var string      $heading
 * @var string      $body
 * @var string|null $primaryLabel
 * @var string|null $primaryHref
 */
?>
<div class="wrap wrap--narrow">
    <div style="padding:6rem 0;text-align:center">
        <h1 class="t-xl"><?= e($heading) ?></h1>
        <p class="muted" style="margin-top:1rem"><?= e($body) ?></p>
        <div class="row" style="justify-content:center;margin-top:2rem">
            <a class="btn" href="<?= e($primaryHref ?? '/boutique') ?>"><?= e($primaryLabel ?? 'Voir le catalogue') ?></a>
            <a class="btn btn--ghost" href="/">Accueil</a>
        </div>
    </div>
</div>
