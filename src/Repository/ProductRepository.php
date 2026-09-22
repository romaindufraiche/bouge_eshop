<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;
use Bouge\Support\Slug;
use Bouge\Support\Status;

/**
 * Accès aux produits.
 *
 * Le filtre « publié » est appliqué ici, une fois pour toutes, dans les
 * méthodes destinées au site public : aucun brouillon ne peut fuiter parce
 * qu'une page aurait oublié de filtrer.
 */
final class ProductRepository
{
    /** Colonnes nécessaires à l'affichage d'une vignette. */
    private const CARD_FIELDS = 'p.id, p.name, p.slug, p.price_cents, p.sale_price_cents,
        p.sale_starts_at, p.sale_ends_at, p.external_url, p.external_label,
        p.available_in_store, c.name AS category_name, c.slug AS category_slug';

    /**
     * Produits en ligne, éventuellement filtrés sur une catégorie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function published(?int $categoryId = null): array
    {
        $sql = 'SELECT ' . self::CARD_FIELDS . '
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE p.status = ?';
        $params = [Status::PRODUCT_PUBLISHED];

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        $sql .= ' ORDER BY p.created_at DESC';

        return $this->withCovers(Database::all($sql, $params));
    }

    /**
     * Sélection de la page d'accueil : les promotions d'abord, complétées par
     * les nouveautés.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forHomepage(int $limit = 8): array
    {
        $rows = Database::all(
            'SELECT ' . self::CARD_FIELDS . '
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.status = ?
             ORDER BY (p.sale_price_cents IS NOT NULL) DESC, p.created_at DESC
             LIMIT ' . (int) $limit,
            [Status::PRODUCT_PUBLISHED]
        );

        return $this->withCovers($rows);
    }

    /**
     * Produit mis en avant en haut de l'accueil.
     * Si plusieurs sont cochés, seul le plus récemment modifié est retenu :
     * une mise en avant qui en affiche trois n'en est plus une.
     *
     * @return array<string, mixed>|null
     */
    public function highlighted(): ?array
    {
        $row = Database::first(
            'SELECT ' . self::CARD_FIELDS . ', p.description
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.status = ? AND p.featured = 1
             ORDER BY p.updated_at DESC
             LIMIT 1',
            [Status::PRODUCT_PUBLISHED]
        );

        if ($row === null) {
            return null;
        }

        return $this->withCovers([$row])[0];
    }

    /**
     * Fiche complète, avec photos et déclinaisons.
     * Renvoie null si le produit n'est pas publié.
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug): ?array
    {
        $product = Database::first(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.status = ?',
            [$slug, Status::PRODUCT_PUBLISHED]
        );

        if ($product === null) {
            return null;
        }

        return $this->withRelations($product);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $product = Database::first(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?',
            [$id]
        );

        return $product === null ? null : $this->withRelations($product);
    }

    /**
     * Produits de la même catégorie, pour le bloc « À voir aussi ».
     *
     * @return array<int, array<string, mixed>>
     */
    public function related(int $categoryId, int $excludeId, int $limit = 4): array
    {
        $rows = Database::all(
            'SELECT ' . self::CARD_FIELDS . '
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.status = ? AND p.category_id = ? AND p.id <> ?
             ORDER BY p.created_at DESC
             LIMIT ' . (int) $limit,
            [Status::PRODUCT_PUBLISHED, $categoryId, $excludeId]
        );

        return $this->withCovers($rows);
    }

    /**
     * Liste de l'administration : recherche, filtre de statut et tri.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(string $search = '', string $status = '', string $sort = 'recent'): array
    {
        $sql = 'SELECT p.*, c.name AS category_name,
                       (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                       (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) AS variant_stock
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            // LIKE avec caractères d'échappement : un « % » saisi par
            // l'utilisateur doit être cherché tel quel, pas interprété.
            $sql .= ' AND p.name LIKE ?';
            $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        }

        if (in_array($status, [Status::PRODUCT_DRAFT, Status::PRODUCT_PUBLISHED], true)) {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }

        // Le tri vient d'une liste fermée : jamais concaténé depuis la requête.
        $sql .= ' ORDER BY ' . match ($sort) {
            'nom'       => 'p.name ASC',
            'prix-asc'  => 'p.price_cents ASC',
            'prix-desc' => 'p.price_cents DESC',
            'stock'     => 'p.stock ASC',
            default     => 'p.created_at DESC',
        };

        return $this->withCovers(Database::all($sql, $params));
    }

    // --- Écriture ------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['name'], null);

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        Database::run(
            'INSERT INTO products (' . implode(', ', $columns) . ") VALUES ({$placeholders})",
            array_values($data)
        );

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['name'], $id);

        $assignments = implode(', ', array_map(
            static fn (string $column): string => "{$column} = ?",
            array_keys($data)
        ));

        Database::run(
            "UPDATE products SET {$assignments} WHERE id = ?",
            [...array_values($data), $id]
        );
    }

    public function delete(int $id): void
    {
        // Photos et déclinaisons partent en cascade (voir le schéma) ; les
        // lignes de commande conservent leurs libellés recopiés.
        Database::run('DELETE FROM products WHERE id = ?', [$id]);
    }

    public function uniqueSlug(string $source, ?int $ignoreId): string
    {
        return Slug::unique($source, static function (string $candidate) use ($ignoreId): bool {
            $row = Database::first('SELECT id FROM products WHERE slug = ?', [$candidate]);

            return $row !== null && (int) $row['id'] !== $ignoreId;
        });
    }

    // --- Assemblage ----------------------------------------------------------

    /**
     * Ajoute la photo de couverture à une liste de produits, en une seule
     * requête plutôt qu'une par produit.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function withCovers(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $ids = array_map(static fn (array $p): int => (int) $p['id'], $products);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $images = Database::all(
            "SELECT product_id, url, alt FROM product_images
             WHERE product_id IN ({$placeholders})
             ORDER BY product_id, position ASC",
            $ids
        );

        $covers = [];
        foreach ($images as $image) {
            $covers[(int) $image['product_id']] ??= $image;
        }

        foreach ($products as &$product) {
            $product['cover'] = $covers[(int) $product['id']] ?? null;
        }

        return $products;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function withRelations(array $product): array
    {
        $id = (int) $product['id'];

        $product['images'] = Database::all(
            'SELECT id, url, alt, position FROM product_images
             WHERE product_id = ? ORDER BY position ASC, id ASC',
            [$id]
        );

        $product['variants'] = Database::all(
            'SELECT id, size, color, sku, stock, price_cents, position FROM product_variants
             WHERE product_id = ? ORDER BY position ASC, id ASC',
            [$id]
        );

        $product['cover'] = $product['images'][0] ?? null;

        return $product;
    }
}
