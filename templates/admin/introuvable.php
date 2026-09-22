<?php
/**
 * Élément demandé absent : supprimé entre-temps, ou adresse tapée à la main.
 *
 * @var string|null $title
 */
?>
<header class="admin-head">
    <h1 class="t-l"><?= e($title ?? 'Introuvable') ?></h1>
</header>

<p class="muted">
    Cet élément n'existe plus. Il a peut-être été supprimé depuis un autre onglet.
</p>

<p style="margin-top:1.5rem">
    <a class="btn btn--ghost" href="/admin/produits">Voir les produits</a>
    <a class="btn btn--ghost" href="/admin/commandes">Voir les commandes</a>
</p>
