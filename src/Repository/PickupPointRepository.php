<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;

/** Points de retrait : boutique, club, piscine partenaire. */
final class PickupPointRepository
{
    /** @return array<int, array<string, mixed>> */
    public function active(): array
    {
        return Database::all(
            'SELECT * FROM pickup_points WHERE is_active = 1 ORDER BY position ASC, name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findActive(int $id): ?array
    {
        return Database::first('SELECT * FROM pickup_points WHERE id = ? AND is_active = 1', [$id]);
    }

    // --- Administration --------------------------------------------------------

    /**
     * Tous les points, actifs ou non, avec le nombre de commandes qui s'y
     * rattachent : c'est ce nombre qui décide si un point peut être supprimé.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        return Database::all(
            'SELECT p.*, (SELECT COUNT(*) FROM orders o WHERE o.pickup_point_id = p.id) AS order_count
             FROM pickup_points p
             ORDER BY p.is_active DESC, p.position ASC, p.name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::first('SELECT * FROM pickup_points WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        Database::run(
            'INSERT INTO pickup_points (' . implode(', ', $columns) . ") VALUES ({$placeholders})",
            array_values($data)
        );

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $assignments = implode(', ', array_map(
            static fn (string $column): string => "{$column} = ?",
            array_keys($data)
        ));

        Database::run(
            "UPDATE pickup_points SET {$assignments} WHERE id = ?",
            [...array_values($data), $id]
        );
    }

    /**
     * Supprime le point s'il n'est rattaché à aucune commande. Sinon on
     * refuse : la commande perdrait l'adresse à laquelle le client doit se
     * présenter. Dans ce cas il faut désactiver le point, ce qui le retire du
     * tunnel sans toucher à l'historique.
     */
    public function delete(int $id): bool
    {
        $count = (int) Database::run(
            'SELECT COUNT(*) FROM orders WHERE pickup_point_id = ?',
            [$id]
        )->fetchColumn();

        if ($count > 0) {
            return false;
        }

        Database::run('DELETE FROM pickup_points WHERE id = ?', [$id]);

        return true;
    }
}
