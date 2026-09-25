<?php
/**
 * Liste des produits : recherche, filtre de statut, tri.
 *
 * @var array<int, array<string,mixed>> $products
 * @var array<int, array<string,mixed>> $categories
 * @var string                          $search
 * @var string                          $status
 * @var string                          $sort
 */

use Bouge\Support\Money;
use Bouge\Support\Pricing;
use Bouge\Support\Status;

$sorts = [
    'recent'    => 'Les plus récents',
    'nom'       => 'Nom (A → Z)',
    'prix-asc'  => 'Prix croissant',
    'prix-desc' => 'Prix décroissant',
    'stock'     => 'Stock le plus bas',
];
?>
<header class="admin-head between">
    <div>
        <h1 class="t-l">Produits</h1>
        <p class="muted t-s"><?= count($products) ?> produit<?= count($products) > 1 ? 's' : '' ?> affiché<?= count($products) > 1 ? 's' : '' ?>.</p>
    </div>
    <a class="btn" href="/admin/produits/nouveau">Ajouter un produit</a>
</header>

<?php /* Formulaire en GET : les filtres restent dans l'adresse, la page peut
         être rechargée ou mise en favori sans rien perdre. */ ?>
<form class="admin-filters" method="get" action="/admin/produits">
    <div class="field">
        <label for="recherche">Rechercher</label>
        <input type="search" id="recherche" name="recherche" value="<?= e($search) ?>"
               placeholder="Nom du produit">
    </div>

    <div class="field">
        <label for="statut">Statut</label>
        <select id="statut" name="statut">
            <option value="">Tous</option>
            <?php foreach (Status::productStatuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="tri">Trier par</label>
        <select id="tri" name="tri">
            <?php foreach ($sorts as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button class="btn btn--ghost" type="submit">Appliquer</button>
        <?php if ($search !== '' || $status !== '' || $sort !== 'recent'): ?>
            <a class="link-quiet t-s" href="/admin/produits">Tout afficher</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($products === []): ?>
    <p class="muted" style="margin-top:2rem">
        Aucun produit ne correspond. Modifiez la recherche, ou
        <a href="/admin/produits/nouveau">ajoutez un produit</a>.
    </p>
<?php else: ?>
    <table class="admin-table admin-table--stack admin-table--products">
        <thead>
            <tr>
                <th scope="col">Produit</th>
                <th scope="col">Catégorie</th>
                <th scope="col" class="ta-right">Prix</th>
                <th scope="col" class="ta-right">Stock</th>
                <th scope="col">Statut</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $price = Pricing::effective($product);
                $isExternal = !empty($product['external_url']);
                // Le stock réel est celui des déclinaisons dès qu'il y en a.
                $stock = (int) $product['variant_count'] > 0
                    ? (int) $product['variant_stock']
                    : (int) $product['stock'];
                ?>
                <tr>
                    <td>
                        <div class="admin-product">
                            <div class="admin-product__media">
                                <?php if ($product['cover'] !== null): ?>
                                    <img src="<?= e($product['cover']['url']) ?>"
                                         alt="" width="56" height="56" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="/admin/produits/<?= (int) $product['id'] ?>">
                                    <?= e($product['name']) ?>
                                </a>
                                <?php if (!empty($product['featured'])): ?>
                                    <span class="pill pill--outline-accent t-xs">Mis en avant</span>
                                <?php endif; ?>
                                <?php /* Importé pour la démonstration : à retirer
                                         quand le vrai catalogue arrive. */ ?>
                                <?php if (!empty($product['demo_source'])): ?>
                                    <span class="pill t-xs" title="Produit de démonstration importé depuis <?= e($product['demo_source']) ?>">Démo</span>
                                <?php endif; ?>
                                <?php if ($isExternal): ?>
                                    <span class="t-xs muted d-block">Vendu par <?= e($product['external_label'] ?: 'un revendeur') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="t-s" data-label="Catégorie"><?= e($product['category_name']) ?></td>
                    <td class="ta-right nums t-s" data-label="Prix">
                        <?php if ($isExternal && (int) $product['price_cents'] === 0): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <?= e(Money::format($price->cents)) ?>
                            <?php if ($price->onSale()): ?>
                                <span class="t-xs muted d-block">au lieu de <?= e(Money::format((int) $product['price_cents'])) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="ta-right nums t-s" data-label="Stock">
                        <?php if ($isExternal): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <span class="<?= $stock === 0 ? 'pill pill--accent' : '' ?>"><?= $stock ?></span>
                            <?php if ((int) $product['variant_count'] > 0): ?>
                                <span class="t-xs muted d-block"><?= (int) $product['variant_count'] ?> déclinaisons</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Statut">
                        <span class="pill <?= $product['status'] === Status::PRODUCT_PUBLISHED ? 'pill--ink' : '' ?>">
                            <?= e(Status::productStatuses()[$product['status']] ?? $product['status']) ?>
                        </span>
                    </td>
                    <td class="ta-right" data-label="Actions">
                        <a class="link-quiet t-s" href="/admin/produits/<?= (int) $product['id'] ?>">Modifier</a>
                        <?php if ($product['status'] === Status::PRODUCT_PUBLISHED): ?>
                            <a class="link-quiet t-s" href="/produit/<?= e($product['slug']) ?>"
                               target="_blank" rel="noopener">Voir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
