<?php
/**
 * Gestion des catégories : créer, renommer, supprimer.
 *
 * Un seul formulaire sert aux deux cas : vide il crée, prérempli il modifie.
 *
 * @var array<int, array<string,mixed>> $categories
 * @var array<string, string>           $errors
 * @var array<string, string>           $values
 * @var int                             $editingId
 */

use Bouge\Support\Csrf;

// Catégorie en cours de modification, s'il y en a une.
$editing = null;
foreach ($categories as $category) {
    if ((int) $category['id'] === (int) ($_GET['modifier'] ?? 0) || (int) $category['id'] === $editingId) {
        $editing = $category;
        break;
    }
}

$value = static function (string $key) use ($values, $editing): string {
    if (array_key_exists($key, $values)) {
        return (string) $values[$key];
    }

    return (string) ($editing[$key] ?? '');
};
?>
<header class="admin-head">
    <h1 class="t-l">Catégories</h1>
    <p class="muted t-s">Les rayons de la boutique. Chaque produit appartient à une catégorie.</p>
</header>

<div class="admin-columns admin-columns--aside">
    <section>
        <?php if ($categories === []): ?>
            <p class="muted">Aucune catégorie. Créez-en une pour pouvoir ajouter des produits.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col" class="ta-right">Produits</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <strong><?= e($category['name']) ?></strong>
                                <?php if (!empty($category['description'])): ?>
                                    <span class="t-xs muted d-block"><?= e($category['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ta-right nums"><?= (int) $category['product_count'] ?></td>
                            <td class="ta-right admin-table__actions">
                                <a class="link-quiet t-s" href="/admin/categories?modifier=<?= (int) $category['id'] ?>#formulaire">
                                    Modifier
                                </a>

                                <?php
                                $nombre = (int) $category['product_count'];
                                // Les autres rayons, candidats à l'accueil des produits.
                                $ailleurs = array_filter(
                                    $categories,
                                    static fn (array $c): bool => (int) $c['id'] !== (int) $category['id']
                                );
                                ?>
                                <?php /* Confirmation en deux temps, sans JavaScript. */ ?>
                                <details class="confirm confirm--inline">
                                    <summary class="link-danger t-s">Supprimer</summary>
                                    <form method="post" action="/admin/categories/supprimer" class="stack-s">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">

                                        <?php if ($nombre === 0): ?>
                                            <p class="t-xs">Supprimer « <?= e($category['name']) ?> » ?</p>
                                        <?php else: ?>
                                            <?php /* Une catégorie pleine ne se refuse plus : elle
                                                     demande ce que deviennent ses produits. Les
                                                     refuser revenait à ne jamais pouvoir supprimer
                                                     un rayon. */ ?>
                                            <p class="t-xs">
                                                « <?= e($category['name']) ?> » contient
                                                <?= $nombre ?> produit<?= $nombre > 1 ? 's' : '' ?>.
                                                Qu'en faire&nbsp;?
                                            </p>

                                            <?php if ($ailleurs !== []): ?>
                                                <label class="choix t-xs">
                                                    <input type="radio" name="produits" value="deplacer" checked>
                                                    <span>
                                                        Les déplacer vers
                                                        <select name="destination" aria-label="Catégorie d'accueil">
                                                            <?php foreach ($ailleurs as $autre): ?>
                                                                <option value="<?= (int) $autre['id'] ?>"><?= e($autre['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </span>
                                                </label>
                                            <?php endif; ?>

                                            <label class="choix t-xs">
                                                <input type="radio" name="produits" value="supprimer"
                                                       <?= $ailleurs === [] ? 'checked' : '' ?>>
                                                <span>
                                                    Les supprimer avec la catégorie
                                                    <span class="d-block muted">
                                                        Définitif. Les commandes déjà passées gardent
                                                        leur libellé&nbsp;: l'historique des ventes
                                                        ne bouge pas.
                                                    </span>
                                                </span>
                                            </label>
                                        <?php endif; ?>

                                        <button class="btn btn--danger btn--sm" type="submit">Oui, supprimer</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <aside class="admin-card" id="formulaire">
        <h2 class="t-m"><?= $editing === null ? 'Nouvelle catégorie' : 'Modifier la catégorie' ?></h2>

        <form method="post" action="/admin/categories/enregistrer" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $editing === null ? 0 : (int) $editing['id'] ?>">

            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="<?= e($value('name')) ?>"
                       maxlength="80" required>
                <?php if (isset($errors['name'])): ?>
                    <p class="field-error"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Description (facultatif)</label>
                <textarea id="description" name="description" rows="3" maxlength="300"><?= e($value('description')) ?></textarea>
                <p class="field-help">Affichée en haut de la page de la catégorie.</p>
                <?php if (isset($errors['description'])): ?>
                    <p class="field-error"><?= e($errors['description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="admin-actions">
                <button class="btn" type="submit">
                    <?= $editing === null ? 'Créer la catégorie' : 'Enregistrer' ?>
                </button>
                <?php if ($editing !== null): ?>
                    <a class="link-quiet" href="/admin/categories">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>
