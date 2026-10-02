<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;
use Bouge\Support\Slug;

/**
 * Accès aux catégories.
 */
final class CategoryRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Database::all(
            'SELECT id, name, slug, description, position, meta_title, meta_description
             FROM categories
             ORDER BY position ASC, name ASC'
        );
    }

    /** Catégories avec le nombre de produits qu'elles contiennent. */
    /** @return array<int, array<string, mixed>> */
    public function allWithCounts(): array
    {
        return Database::all(
            'SELECT c.id, c.name, c.slug, c.description, c.position,
                    COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.position ASC, c.name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return Database::first(
            'SELECT id, name, slug, description, meta_title, meta_description
             FROM categories WHERE slug = ?',
            [$slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::first('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public function create(string $name, ?string $description): int
    {
        $slug = $this->uniqueSlug($name, null);

        // La nouvelle catégorie se place en fin de liste.
        $position = (int) Database::run('SELECT COALESCE(MAX(position), -1) + 1 FROM categories')
            ->fetchColumn();

        Database::run(
            'INSERT INTO categories (name, slug, description, position) VALUES (?, ?, ?, ?)',
            [$name, $slug, $description, $position]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        Database::run(
            'UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?',
            [$name, $this->uniqueSlug($name, $id), $description, $id]
        );
    }

    /**
     * Supprime une catégorie vide.
     * Renvoie false si elle contient encore des produits : ils se
     * retrouveraient sans rayon, et la contrainte de clé étrangère refuserait
     * de toute façon la suppression.
     */
    public function delete(int $id): bool
    {
        if ($this->productCount($id) > 0) {
            return false;
        }

        Database::run('DELETE FROM categories WHERE id = ?', [$id]);

        return true;
    }

    public function productCount(int $id): int
    {
        return (int) Database::run('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])
            ->fetchColumn();
    }

    /**
     * Déplace les produits d'une catégorie vers une autre, puis supprime la
     * première. Les deux en une transaction : une catégorie vidée mais pas
     * supprimée laisserait un rayon fantôme, et des produits déplacés vers
     * une catégorie disparue ne pointeraient plus sur rien.
     *
     * @return int Le nombre de produits déplacés.
     */
    public function deleteMovingProductsTo(int $id, int $destination): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $deplaces = Database::run(
                'UPDATE products SET category_id = ? WHERE category_id = ?',
                [$destination, $id]
            )->rowCount();

            Database::run('DELETE FROM categories WHERE id = ?', [$id]);

            $pdo->commit();

            return $deplaces;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /**
     * Les photos des produits d'une catégorie, pour que l'appelant retire les
     * fichiers du disque avant de supprimer les lignes. Dans l'autre ordre, un
     * incident laisserait des images orphelines que plus rien ne désigne.
     *
     * @return list<string>
     */
    public function productImageUrls(int $id): array
    {
        $lignes = Database::all(
            'SELECT i.url FROM product_images i
             JOIN products p ON p.id = i.product_id
             WHERE p.category_id = ?',
            [$id]
        );

        return array_map(static fn (array $l): string => (string) $l['url'], $lignes);
    }

    /**
     * Supprime la catégorie avec ses produits.
     *
     * Les photos et les déclinaisons partent en cascade ; les lignes de
     * commande, elles, gardent le nom et le prix recopiés au moment de
     * l'achat — l'historique des ventes survit à la disparition du produit.
     *
     * @return int Le nombre de produits supprimés.
     */
    public function deleteWithProducts(int $id): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $supprimes = Database::run('DELETE FROM products WHERE category_id = ?', [$id])->rowCount();
            Database::run('DELETE FROM categories WHERE id = ?', [$id]);

            $pdo->commit();

            return $supprimes;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        return Slug::unique($name, static function (string $candidate) use ($ignoreId): bool {
            $row = Database::first('SELECT id FROM categories WHERE slug = ?', [$candidate]);

            return $row !== null && (int) $row['id'] !== $ignoreId;
        });
    }
}
