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
        $count = (int) Database::run('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])
            ->fetchColumn();

        if ($count > 0) {
            return false;
        }

        Database::run('DELETE FROM categories WHERE id = ?', [$id]);

        return true;
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        return Slug::unique($name, static function (string $candidate) use ($ignoreId): bool {
            $row = Database::first('SELECT id FROM categories WHERE slug = ?', [$candidate]);

            return $row !== null && (int) $row['id'] !== $ignoreId;
        });
    }
}
