<?php
/**
 * Fiche produit : création et modification.
 *
 * Les photos et les suppressions passent par des formulaires distincts : un
 * formulaire HTML ne peut pas en contenir un autre, et on ne veut pas qu'un
 * envoi de photo réenregistre toute la fiche.
 *
 * @var array<string, mixed>|null       $product
 * @var array<int, array<string,mixed>> $categories
 * @var array<string, string>           $errors
 * @var array<string, string>           $values
 * @var array<int, array<string,mixed>> $variants
 */

use Bouge\Support\Csrf;
use Bouge\Support\Money;
use Bouge\Support\Status;
use Bouge\Support\Usage;

$isNew = $product === null;
$id = $isNew ? 0 : (int) $product['id'];
$resubmitted = $values !== [];

/**
 * Valeur d'un champ : ce qui vient d'être saisi s'il y a eu une erreur,
 * sinon la valeur enregistrée.
 */
$field = static function (string $key, mixed $fallback = '') use ($values, $product, $resubmitted): string {
    if (array_key_exists($key, $values)) {
        return (string) $values[$key];
    }

    if (!$resubmitted && $product !== null && isset($product[$key]) && $product[$key] !== null) {
        return (string) $product[$key];
    }

    return (string) $fallback;
};

/** Cases à cocher : une case non cochée n'est pas envoyée du tout. */
$checked = static function (string $key) use ($values, $product, $resubmitted): bool {
    if ($resubmitted) {
        return array_key_exists($key, $values);
    }

    return $product !== null && !empty($product[$key]);
};

$priceValue = $field('price', $isNew ? '' : Money::toInput((int) $product['price_cents']));
$saleValue = $field(
    'sale_price',
    $isNew || $product['sale_price_cents'] === null ? '' : Money::toInput((int) $product['sale_price_cents'])
);
?>
<header class="admin-head between">
    <div>
        <p class="t-xs"><a class="link-quiet" href="/admin/produits">← Tous les produits</a></p>
        <h1 class="t-l"><?= $isNew ? 'Nouveau produit' : e($product['name']) ?></h1>
        <?php if (!$isNew && $product['status'] === Status::PRODUCT_PUBLISHED): ?>
            <p class="t-xs">
                <a href="/produit/<?= e($product['slug']) ?>" target="_blank" rel="noopener">
                    Voir la fiche sur la boutique
                </a>
            </p>
        <?php endif; ?>
    </div>
</header>

<?php if ($errors !== []): ?>
    <p class="notice notice--accent" role="alert">
        Le formulaire n'a pas été enregistré : corrigez les champs signalés ci-dessous.
    </p>
<?php endif; ?>

<form method="post" action="/admin/produits/enregistrer" class="admin-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <section class="admin-card">
        <h2 class="t-m">L'essentiel</h2>

        <div class="field">
            <label for="name">Nom du produit</label>
            <input type="text" id="name" name="name" value="<?= e($field('name')) ?>" required
                   maxlength="160" autofocus>
            <?php if (isset($errors['name'])): ?>
                <p class="field-error"><?= e($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="8" required><?= e($field('description')) ?></textarea>
            <p class="field-help">Ce texte s'affiche sur la fiche du produit. Les retours à la ligne sont conservés.</p>
            <?php if (isset($errors['description'])): ?>
                <p class="field-error"><?= e($errors['description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="category_id">Catégorie</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= $field('category_id') === (string) $category['id'] ? ' selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?>
                    <p class="field-error"><?= e($errors['category_id']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <?php foreach (Status::productStatuses() as $value => $label): ?>
                        <option value="<?= e($value) ?>"
                            <?= $field('status', Status::PRODUCT_DRAFT) === $value ? ' selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">Un brouillon n'est visible que depuis cette administration.</p>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <h2 class="t-m">Prix et stock</h2>

        <div class="field-row">
            <div class="field">
                <label for="price">Prix de vente (€)</label>
                <input type="text" inputmode="decimal" id="price" name="price"
                       value="<?= e($priceValue) ?>" placeholder="14,90" required>
                <?php if (isset($errors['price'])): ?>
                    <p class="field-error"><?= e($errors['price']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" step="1"
                       value="<?= e($field('stock', '0')) ?>">
                <p class="field-help">Ignoré si le produit a des déclinaisons : c'est alors leur stock qui compte.</p>
            </div>

            <div class="field">
                <label for="weight_grams">Poids en grammes</label>
                <input type="number" id="weight_grams" name="weight_grams" min="0" step="1"
                       value="<?= e($field('weight_grams', '')) ?>" placeholder="120">
                <p class="field-help">
                    Le transporteur facture au poids. Laissé vide, le poids par défaut de la
                    configuration s'applique — mieux vaut une estimation haute qu'un colis
                    refusé au dépôt.
                </p>
            </div>
        </div>

        <fieldset class="field-group">
            <legend>Promotion (facultatif)</legend>

            <div class="field-row">
                <div class="field">
                    <label for="sale_price">Prix promotionnel (€)</label>
                    <input type="text" inputmode="decimal" id="sale_price" name="sale_price"
                           value="<?= e($saleValue) ?>" placeholder="11,90">
                    <?php if (isset($errors['sale_price'])): ?>
                        <p class="field-error"><?= e($errors['sale_price']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="sale_starts_at">Du</label>
                    <input type="date" id="sale_starts_at" name="sale_starts_at"
                           value="<?= e($field('sale_starts_at')) ?>">
                </div>

                <div class="field">
                    <label for="sale_ends_at">Au</label>
                    <input type="date" id="sale_ends_at" name="sale_ends_at"
                           value="<?= e($field('sale_ends_at')) ?>">
                    <?php if (isset($errors['sale_ends_at'])): ?>
                        <p class="field-error"><?= e($errors['sale_ends_at']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <p class="field-help">
                Laissez les dates vides pour une promotion sans limite. Le prix barré affiché
                au client est le prix de vente normal.
            </p>
        </fieldset>
    </section>

    <section class="admin-card">
        <h2 class="t-m">Déclinaisons</h2>
        <p class="field-help">
            Tailles et couleurs proposées au client. Laissez tout vide si le produit
            n'en a qu'une seule version. Pour supprimer une déclinaison, effacez sa
            taille et sa couleur.
        </p>

        <?php if (isset($errors['variants'])): ?>
            <p class="field-error"><?= e($errors['variants']) ?></p>
        <?php endif; ?>

        <table class="admin-table admin-table--variants">
            <thead>
                <tr>
                    <th scope="col">Taille</th>
                    <th scope="col">Couleur</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Prix propre (€)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($variants as $index => $variant): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="variant_id[<?= $index ?>]" value="<?= (int) $variant['id'] ?>">
                            <label class="sr-only" for="variant_size_<?= $index ?>">Taille de la déclinaison <?= $index + 1 ?></label>
                            <input type="text" id="variant_size_<?= $index ?>" name="variant_size[<?= $index ?>]"
                                   value="<?= e($variant['size']) ?>" maxlength="40" placeholder="M">
                        </td>
                        <td>
                            <label class="sr-only" for="variant_color_<?= $index ?>">Couleur de la déclinaison <?= $index + 1 ?></label>
                            <input type="text" id="variant_color_<?= $index ?>" name="variant_color[<?= $index ?>]"
                                   value="<?= e($variant['color']) ?>" maxlength="40" placeholder="Noir">
                        </td>
                        <td>
                            <label class="sr-only" for="variant_stock_<?= $index ?>">Stock de la déclinaison <?= $index + 1 ?></label>
                            <input type="number" id="variant_stock_<?= $index ?>" name="variant_stock[<?= $index ?>]"
                                   value="<?= (int) $variant['stock'] ?>" min="0" step="1">
                        </td>
                        <td>
                            <label class="sr-only" for="variant_price_<?= $index ?>">Prix propre de la déclinaison <?= $index + 1 ?></label>
                            <input type="text" inputmode="decimal" id="variant_price_<?= $index ?>"
                                   name="variant_price[<?= $index ?>]" value="<?= e($variant['price']) ?>"
                                   placeholder="identique">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="field-help">
            Trois lignes vides sont toujours proposées. Enregistrez pour en obtenir
            trois nouvelles.
        </p>
    </section>

    <section class="admin-card">
        <h2 class="t-m">Usages</h2>
        <p class="field-help">
            À quoi sert ce produit ? Ces cases décident des pages sur lesquelles
            il apparaît (« Entraînement », « Compétition »…) et du filtre du
            catalogue. Un produit peut en cocher plusieurs.
        </p>

        <?php
        // Après une erreur de validation, on réaffiche ce qui venait d'être
        // coché plutôt que ce qui est enregistré.
        $usagesCoches = $resubmitted
            ? array_map('strval', (array) ($_POST['usages'] ?? []))
            : Usage::toList($product['usages'] ?? null);
        ?>

        <div class="cases">
            <?php foreach (Usage::all() as $slug => $libelle): ?>
                <label class="check">
                    <input type="checkbox" name="usages[]" value="<?= e($slug) ?>"
                        <?= in_array($slug, $usagesCoches, true) ? ' checked' : '' ?>>
                    <span><?= e($libelle) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-card">
        <h2 class="t-m">Mise en avant et vente en magasin</h2>

        <label class="check">
            <input type="checkbox" name="featured" value="1"<?= $checked('featured') ? ' checked' : '' ?>>
            <span>Mettre ce produit en avant sur la page d'accueil</span>
        </label>

        <label class="check">
            <input type="checkbox" name="available_in_store" value="1"<?= $checked('available_in_store') ? ' checked' : '' ?>>
            <span>Disponible en magasin</span>
        </label>

        <div class="field-row">
            <div class="field">
                <label for="external_url">Lien d'achat chez un revendeur (facultatif)</label>
                <input type="url" id="external_url" name="external_url"
                       value="<?= e($field('external_url')) ?>" placeholder="https://…">
                <p class="field-help">
                    Renseigné, le produit n'est plus ajoutable au panier : le client est
                    envoyé vers ce lien.
                </p>
                <?php if (isset($errors['external_url'])): ?>
                    <p class="field-error"><?= e($errors['external_url']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="external_label">Nom du revendeur</label>
                <input type="text" id="external_label" name="external_label"
                       value="<?= e($field('external_label')) ?>" placeholder="la Fnac" maxlength="80">
            </div>
        </div>
    </section>

    <section class="admin-card">
        <h2 class="t-m">Référencement</h2>
        <p class="field-help">
            Facultatif : sans ces champs, le nom et le début de la description sont
            utilisés.
        </p>

        <div class="field">
            <label for="meta_title">Titre dans Google</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= e($field('meta_title')) ?>"
                   maxlength="70">
            <?php if (isset($errors['meta_title'])): ?>
                <p class="field-error"><?= e($errors['meta_title']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="meta_description">Description dans Google</label>
            <textarea id="meta_description" name="meta_description" rows="3" maxlength="180"><?= e($field('meta_description')) ?></textarea>
            <?php if (isset($errors['meta_description'])): ?>
                <p class="field-error"><?= e($errors['meta_description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="slug">Adresse de la page</label>
            <div class="field-prefixed">
                <span class="t-s muted"><?= e(url('/produit/')) ?></span>
                <input type="text" id="slug" name="slug" value="<?= e($field('slug')) ?>"
                       placeholder="généré depuis le nom">
            </div>
            <p class="field-help">
                À ne modifier qu'avant la mise en ligne : changer cette adresse casse
                les liens déjà partagés.
            </p>
        </div>
    </section>

    <div class="admin-actions">
        <button class="btn" type="submit">Enregistrer</button>
        <a class="link-quiet" href="/admin/produits">Annuler</a>
    </div>
</form>

<?php if (!$isNew): ?>
    <section class="admin-card">
        <h2 class="t-m">Photos</h2>

        <?php if ($product['images'] === []): ?>
            <p class="muted t-s">Aucune photo pour le moment.</p>
        <?php else: ?>
            <ul class="admin-photos">
                <?php foreach ($product['images'] as $position => $image): ?>
                    <li class="admin-photo">
                        <img src="<?= e($image['url']) ?>" alt="<?= e($image['alt']) ?>"
                             width="200" height="200" loading="lazy">

                        <?php if ($position === 0): ?>
                            <p class="t-xs"><span class="pill pill--ink">Photo principale</span></p>
                        <?php endif; ?>

                        <?php /* Texte alternatif : lu par les lecteurs d'écran et
                                 affiché si l'image ne charge pas. */ ?>
                        <form method="post" action="/admin/produits/photo/texte" class="admin-photo__alt">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                            <label class="sr-only" for="alt_<?= (int) $image['id'] ?>">
                                Description de la photo <?= $position + 1 ?>
                            </label>
                            <input type="text" id="alt_<?= (int) $image['id'] ?>" name="alt"
                                   value="<?= e($image['alt']) ?>" maxlength="200">
                            <button class="btn btn--ghost btn--sm" type="submit">Enregistrer</button>
                        </form>

                        <div class="admin-photo__actions">
                            <form method="post" action="/admin/produits/photo/deplacer">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button class="btn btn--ghost btn--sm" type="submit"
                                    <?= $position === 0 ? ' disabled' : '' ?>>← Avancer</button>
                            </form>

                            <form method="post" action="/admin/produits/photo/deplacer">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button class="btn btn--ghost btn--sm" type="submit"
                                    <?= $position === count($product['images']) - 1 ? ' disabled' : '' ?>>Reculer →</button>
                            </form>

                            <?php /* Confirmation en deux temps, sans JavaScript :
                                     le bouton rouge n'apparaît qu'après ouverture. */ ?>
                            <details class="confirm">
                                <summary class="link-danger t-s">Supprimer</summary>
                                <form method="post" action="/admin/produits/photo/supprimer">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                    <p class="t-xs">Supprimer définitivement cette photo ?</p>
                                    <button class="btn btn--danger btn--sm" type="submit">Oui, supprimer</button>
                                </form>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/admin/produits/photos" enctype="multipart/form-data"
              class="admin-upload">
            <?= Csrf::field() ?>
            <input type="hidden" name="product_id" value="<?= $id ?>">

            <div class="field">
                <label for="photos">Ajouter des photos</label>
                <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                <p class="field-help">
                    Formats acceptés : JPEG, PNG, WebP. 5 Mo maximum par photo.
                    La première photo de la liste sert de vignette.
                </p>
            </div>

            <button class="btn btn--ghost" type="submit">Envoyer</button>
        </form>
    </section>

    <section class="admin-card admin-card--danger">
        <h2 class="t-m">Supprimer ce produit</h2>
        <p class="t-s muted">
            La fiche et ses photos seront définitivement effacées. Les commandes déjà
            passées gardent le nom et le prix du produit tels qu'ils étaient.
        </p>

        <details class="confirm">
            <summary class="btn btn--danger">Supprimer le produit</summary>
            <form method="post" action="/admin/produits/supprimer" class="stack-s">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <p class="t-s">
                    Confirmez-vous la suppression de « <?= e($product['name']) ?> » ?
                    Cette action est irréversible.
                </p>
                <button class="btn btn--danger" type="submit">Oui, supprimer définitivement</button>
            </form>
        </details>
    </section>
<?php else: ?>
    <p class="muted t-s">
        Les photos pourront être ajoutées une fois la fiche enregistrée.
    </p>
<?php endif; ?>
