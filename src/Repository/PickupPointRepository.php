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
}
