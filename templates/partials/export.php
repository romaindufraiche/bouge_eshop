<?php
/**
 * Le bouton d'export en classeur Excel.
 *
 * Les listes de produits et de commandes en portent chacune un. Ils étaient
 * dessinés deux fois, à deux endroits et sous deux libellés — « Exporter les
 * stocks » coincé sous le titre d'un côté, « Exporter en Excel » en haut à
 * droite de l'autre. Un seul gabarit les tient désormais : même place, même
 * bouton, même mot.
 *
 * L'export reprend les filtres et la recherche en cours : ce qu'on voit à
 * l'écran est ce qu'on télécharge. Un bouton qui renverrait tout après une
 * recherche serait une surprise.
 *
 * @var string $href chemin de l'export, sans paramètres
 * @var string $quoi ce qui est exporté, pour les lecteurs d'écran
 */

// Les filtres de la page en cours, repassés tels quels à l'export.
$parametres = $_GET !== [] ? '?' . http_build_query($_GET) : '';
?>
<a class="btn btn--ghost" href="<?= e($href . $parametres) ?>">
    <?php /* La flèche vers le bas dit « ça descend chez vous » : c'est le
             signe que tout le monde lit sans le libellé. Même trait que la
             loupe de la recherche et le panier de l'en-tête — 1,6 px,
             `currentColor`. */ ?>
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M8 2v8"/>
        <path d="M4.5 7 8 10.5 11.5 7"/>
        <path d="M2.5 13.5h11"/>
    </svg>
    Exporter en Excel
    <span class="sr-only">— <?= e($quoi) ?> affichés</span>
</a>
