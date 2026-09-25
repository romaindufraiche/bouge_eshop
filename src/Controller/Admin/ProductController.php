<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\CategoryRepository;
use Bouge\Repository\ProductRepository;
use Bouge\Support\Auth;
use Bouge\Support\Database;
use Bouge\Support\Money;
use Bouge\Support\Session;
use Bouge\Support\Status;
use Bouge\Support\Usage;
use Bouge\Support\Uploads;
use Bouge\Support\Validator;
use Bouge\Support\View;
use RuntimeException;

final class ProductController
{
    /** Nombre de lignes de déclinaison vides proposées en plus des existantes. */
    private const BLANK_VARIANT_ROWS = 3;

    public function index(): string
    {
        Auth::require();

        $search = trim((string) ($_GET['recherche'] ?? ''));
        $status = (string) ($_GET['statut'] ?? '');
        $sort = (string) ($_GET['tri'] ?? 'recent');

        return View::render('admin/produits', [
            'title'      => 'Produits',
            'products'   => (new ProductRepository())->forAdmin($search, $status, $sort),
            'categories' => (new CategoryRepository())->all(),
            'search'     => $search,
            'status'     => $status,
            'sort'       => $sort,
        ], 'layout/admin');
    }

    public function create(): string
    {
        Auth::require();

        $categories = (new CategoryRepository())->all();

        if ($categories === []) {
            Session::flash('admin', "Créez d'abord une catégorie : chaque produit doit être rangé quelque part.");
            redirect('/admin/categories');
        }

        // Un nouveau produit part en brouillon : on ne publie pas par accident
        // un article incomplet.
        return $this->renderForm(null, [], [
            'status' => Status::PRODUCT_DRAFT,
            'stock'  => '0',
        ]);
    }

    /** @param array<string, string> $params */
    public function edit(array $params): string
    {
        Auth::require();

        $product = (new ProductRepository())->find((int) $params['id']);

        if ($product === null) {
            http_response_code(404);

            return View::render('admin/introuvable', ['title' => 'Produit introuvable'], 'layout/admin');
        }

        return $this->renderForm($product, [], []);
    }

    public function save(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new ProductRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? $repository->find($id) : null;

        if ($id > 0 && $existing === null) {
            Session::flash('admin', "Ce produit n'existe plus.");
            redirect('/admin/produits');
        }

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Donnez un nom au produit.')
            ->maxLength('name', 160, 'Nom trop long (160 caractères maximum).')
            ->required('description', 'Décrivez le produit en quelques lignes.')
            ->required('category_id', 'Choisissez une catégorie.')
            ->required('price', 'Indiquez un prix.')
            ->inList('status', [Status::PRODUCT_DRAFT, Status::PRODUCT_PUBLISHED], 'Statut invalide.')
            ->maxLength('meta_title', 70, 'Titre trop long (70 caractères maximum).')
            ->maxLength('meta_description', 180, 'Description trop longue (180 caractères maximum).');

        $priceCents = Money::fromInput($validator->value('price'));
        if ($validator->value('price') !== '' && $priceCents === null) {
            $validator->fail('price', 'Montant invalide. Exemple : 14,90');
        }

        $saleCents = null;
        if ($validator->value('sale_price') !== '') {
            $saleCents = Money::fromInput($validator->value('sale_price'));
            if ($saleCents === null) {
                $validator->fail('sale_price', 'Montant invalide. Exemple : 11,90');
            } elseif ($priceCents !== null && $saleCents >= $priceCents) {
                $validator->fail('sale_price', 'Le prix promotionnel doit être inférieur au prix normal.');
            }
        }

        $saleStart = $validator->value('sale_starts_at');
        $saleEnd = $validator->value('sale_ends_at');
        if ($saleStart !== '' && $saleEnd !== '' && $saleStart > $saleEnd) {
            $validator->fail('sale_ends_at', 'La date de fin doit venir après la date de début.');
        }

        $externalUrl = $validator->value('external_url');
        if ($externalUrl !== '' && !filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $validator->fail('external_url', 'Lien invalide. Il doit commencer par https://');
        }

        $variants = $this->readVariants();

        // Un produit vendu ailleurs ne passe pas par le panier : ses
        // déclinaisons ne seraient jamais utilisées et laisseraient croire à
        // une vente en ligne.
        if ($externalUrl !== '' && $variants !== []) {
            $validator->fail(
                'external_url',
                "Un produit vendu par un revendeur ne peut pas avoir de déclinaisons : retirez-les, ou videz le lien."
            );
        }

        // Deux déclinaisons ne peuvent pas porter la même combinaison.
        $keys = array_map(
            static fn (array $v): string => mb_strtolower($v['size'] . '|' . $v['color']),
            $variants
        );
        if (count(array_unique($keys)) !== count($keys)) {
            $validator->fail('variants', 'Deux déclinaisons ont la même taille et la même couleur.');
        }

        $category = (new CategoryRepository())->find((int) $validator->value('category_id'));
        if ($category === null) {
            $validator->fail('category_id', "Cette catégorie n'existe plus.");
        }

        if (!$validator->passes()) {
            // 422 plutôt que 200 : la requête était bien formée mais n'a pas
            // été enregistrée. Le navigateur affiche la page normalement.
            http_response_code(422);

            return $this->renderForm($existing, $validator->errors(), $validator->values(), $variants);
        }

        $data = [
            'name'               => $validator->value('name'),
            'slug'               => $validator->value('slug'),
            'description'        => $validator->value('description'),
            'category_id'        => (int) $validator->value('category_id'),
            'price_cents'        => $priceCents ?? 0,
            'sale_price_cents'   => $saleCents,
            'sale_starts_at'     => $saleStart ?: null,
            'sale_ends_at'       => $saleEnd ?: null,
            'status'             => $validator->value('status'),
            'stock'              => max(0, (int) $validator->value('stock')),
            // Vide plutôt que zéro : zéro gramme ferait croire à un poids connu,
            // alors que c'est le poids par défaut qui doit s'appliquer.
            'weight_grams'       => ((int) $validator->value('weight_grams')) > 0
                ? (int) $validator->value('weight_grams')
                : null,
            'featured'           => isset($_POST['featured']) ? 1 : 0,
            'external_url'       => $externalUrl ?: null,
            'external_label'     => $validator->value('external_label') ?: null,
            'available_in_store' => isset($_POST['available_in_store']) ? 1 : 0,
            // Usages cochés dans le formulaire ; les valeurs inconnues sont
            // écartées par Usage::toStorage().
            'usages'             => Usage::toStorage((array) ($_POST['usages'] ?? [])),
            'meta_title'         => $validator->value('meta_title') ?: null,
            'meta_description'   => $validator->value('meta_description') ?: null,
        ];

        if ($existing !== null) {
            $repository->update($id, $data);
            $productId = $id;
        } else {
            $productId = $repository->create($data);
        }

        $this->saveVariants($productId, $variants);

        Session::flash('admin', 'Les modifications ont été enregistrées.');
        redirect('/admin/produits/' . $productId);

        return '';
    }

    public function delete(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new ProductRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $product = $repository->find($id);

        if ($product === null) {
            redirect('/admin/produits');
        }

        // Les fichiers image sont retirés avant la suppression en base : en
        // cas d'échec ici, on ne laisse pas de lignes pointant vers du vide.
        foreach ($product['images'] as $image) {
            Uploads::delete((string) $image['url']);
        }

        $repository->delete($id);

        Session::flash('admin', "Le produit « {$product['name']} » a été supprimé.");
        redirect('/admin/produits');

        return '';
    }

    // --- Photos ---------------------------------------------------------------

    public function uploadImages(): string
    {
        Auth::require();
        Auth::requireToken();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = (new ProductRepository())->find($productId);

        if ($product === null) {
            redirect('/admin/produits');
        }

        /** @var array<string, array<int, mixed>>|null $files */
        $files = $_FILES['photos'] ?? null;

        if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
            Session::flash('admin', 'Choisissez au moins une photo.');
            redirect('/admin/produits/' . $productId);
        }

        $position = (int) Database::run(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM product_images WHERE product_id = ?',
            [$productId]
        )->fetchColumn();

        $saved = 0;
        $errors = [];

        foreach (array_keys($files['name']) as $index) {
            if ((int) $files['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $url = Uploads::store([
                    'name'     => (string) $files['name'][$index],
                    'type'     => (string) $files['type'][$index],
                    'tmp_name' => (string) $files['tmp_name'][$index],
                    'error'    => (int) $files['error'][$index],
                    'size'     => (int) $files['size'][$index],
                ]);

                Database::run(
                    'INSERT INTO product_images (product_id, url, alt, position) VALUES (?, ?, ?, ?)',
                    // Texte alternatif par défaut, modifiable juste après :
                    // mieux vaut une description imparfaite qu'aucune.
                    [$productId, $url, $product['name'], $position++]
                );
                $saved++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            Session::flash('admin', implode(' ', $errors));
        } elseif ($saved === 0) {
            Session::flash('admin', 'Choisissez au moins une photo.');
        } else {
            Session::flash('admin', $saved . ' photo' . ($saved > 1 ? 's ajoutées.' : ' ajoutée.'));
        }

        redirect('/admin/produits/' . $productId);

        return '';
    }

    public function deleteImage(): string
    {
        Auth::require();
        Auth::requireToken();

        $imageId = (int) ($_POST['image_id'] ?? 0);
        $image = Database::first('SELECT * FROM product_images WHERE id = ?', [$imageId]);

        if ($image === null) {
            redirect('/admin/produits');
        }

        Database::run('DELETE FROM product_images WHERE id = ?', [$imageId]);
        Uploads::delete((string) $image['url']);
        $this->renumberImages((int) $image['product_id']);

        redirect('/admin/produits/' . (int) $image['product_id']);

        return '';
    }

    public function moveImage(): string
    {
        Auth::require();
        Auth::requireToken();

        $imageId = (int) ($_POST['image_id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        $image = Database::first('SELECT * FROM product_images WHERE id = ?', [$imageId]);

        if ($image === null || !in_array($direction, ['up', 'down'], true)) {
            redirect('/admin/produits');
        }

        $productId = (int) $image['product_id'];
        $images = Database::all(
            'SELECT id FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC',
            [$productId]
        );

        $index = null;
        foreach ($images as $position => $row) {
            if ((int) $row['id'] === $imageId) {
                $index = $position;
                break;
            }
        }

        $target = $index === null ? null : ($direction === 'up' ? $index - 1 : $index + 1);

        // Déjà en première ou en dernière position : rien à faire.
        if ($target !== null && $target >= 0 && $target < count($images)) {
            [$images[$index], $images[$target]] = [$images[$target], $images[$index]];

            foreach ($images as $position => $row) {
                Database::run(
                    'UPDATE product_images SET position = ? WHERE id = ?',
                    [$position, (int) $row['id']]
                );
            }
        }

        redirect('/admin/produits/' . $productId);

        return '';
    }

    public function updateImageAlt(): string
    {
        Auth::require();
        Auth::requireToken();

        $imageId = (int) ($_POST['image_id'] ?? 0);
        $alt = mb_substr(trim((string) ($_POST['alt'] ?? '')), 0, 200);
        $image = Database::first('SELECT product_id FROM product_images WHERE id = ?', [$imageId]);

        if ($image === null) {
            redirect('/admin/produits');
        }

        if ($alt !== '') {
            Database::run('UPDATE product_images SET alt = ? WHERE id = ?', [$alt, $imageId]);
        }

        redirect('/admin/produits/' . (int) $image['product_id']);

        return '';
    }

    // --- Déclinaisons -----------------------------------------------------------

    /**
     * Lit les lignes de déclinaison du formulaire.
     * Les lignes sans taille ni couleur sont ignorées : ce sont les lignes
     * vides laissées à disposition pour en ajouter.
     *
     * @return array<int, array{id: int, size: string, color: string, stock: int, price: string}>
     */
    private function readVariants(): array
    {
        $ids = $_POST['variant_id'] ?? [];
        $sizes = $_POST['variant_size'] ?? [];
        $colors = $_POST['variant_color'] ?? [];
        $stocks = $_POST['variant_stock'] ?? [];
        $prices = $_POST['variant_price'] ?? [];

        if (!is_array($sizes)) {
            return [];
        }

        $variants = [];

        foreach (array_keys($sizes) as $index) {
            $size = trim((string) ($sizes[$index] ?? ''));
            $color = trim((string) ($colors[$index] ?? ''));

            if ($size === '' && $color === '') {
                continue;
            }

            $variants[] = [
                'id'    => (int) ($ids[$index] ?? 0),
                'size'  => mb_substr($size, 0, 40),
                'color' => mb_substr($color, 0, 40),
                'stock' => max(0, (int) ($stocks[$index] ?? 0)),
                'price' => trim((string) ($prices[$index] ?? '')),
            ];
        }

        return $variants;
    }

    /** @param array<int, array{id: int, size: string, color: string, stock: int, price: string}> $variants */
    private function saveVariants(int $productId, array $variants): void
    {
        $keptIds = array_values(array_filter(array_map(
            static fn (array $v): int => $v['id'],
            $variants
        )));

        // Retirées du formulaire : on les supprime.
        if ($keptIds === []) {
            Database::run('DELETE FROM product_variants WHERE product_id = ?', [$productId]);
        } else {
            $placeholders = implode(', ', array_fill(0, count($keptIds), '?'));
            Database::run(
                "DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ({$placeholders})",
                [$productId, ...$keptIds]
            );
        }

        foreach ($variants as $position => $variant) {
            $priceCents = $variant['price'] === '' ? null : Money::fromInput($variant['price']);

            $values = [
                $variant['size'] ?: null,
                $variant['color'] ?: null,
                $variant['stock'],
                $priceCents,
                $position,
            ];

            if ($variant['id'] > 0) {
                Database::run(
                    'UPDATE product_variants SET size = ?, color = ?, stock = ?, price_cents = ?, position = ?
                     WHERE id = ? AND product_id = ?',
                    [...$values, $variant['id'], $productId]
                );
            } else {
                Database::run(
                    'INSERT INTO product_variants (size, color, stock, price_cents, position, product_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [...$values, $productId]
                );
            }
        }
    }

    private function renumberImages(int $productId): void
    {
        $images = Database::all(
            'SELECT id FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC',
            [$productId]
        );

        foreach ($images as $position => $image) {
            Database::run('UPDATE product_images SET position = ? WHERE id = ?', [$position, (int) $image['id']]);
        }
    }

    /**
     * @param array<string, mixed>|null $product
     * @param array<string, string>     $errors
     * @param array<string, string>     $values
     * @param array<int, array<string, mixed>>|null $submittedVariants
     */
    private function renderForm(?array $product, array $errors, array $values, ?array $submittedVariants = null): string
    {
        // Après une erreur, on réaffiche ce qui a été saisi plutôt que les
        // valeurs d'origine : retaper une fiche entière serait pénible.
        $variants = $submittedVariants ?? array_map(
            static fn (array $v): array => [
                'id'    => (int) $v['id'],
                'size'  => (string) ($v['size'] ?? ''),
                'color' => (string) ($v['color'] ?? ''),
                'stock' => (int) $v['stock'],
                'price' => $v['price_cents'] === null ? '' : Money::toInput((int) $v['price_cents']),
            ],
            $product['variants'] ?? []
        );

        // Lignes vides pour en ajouter : sans JavaScript, on en propose
        // toujours quelques-unes d'avance.
        for ($i = 0; $i < self::BLANK_VARIANT_ROWS; $i++) {
            $variants[] = ['id' => 0, 'size' => '', 'color' => '', 'stock' => 0, 'price' => ''];
        }

        return View::render('admin/produit-formulaire', [
            'title'      => $product === null ? 'Nouveau produit' : $product['name'],
            'product'    => $product,
            'categories' => (new CategoryRepository())->all(),
            'errors'     => $errors,
            'values'     => $values,
            'variants'   => $variants,
        ], 'layout/admin');
    }
}
