<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;

final class AdminUserRepository
{
    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return Database::first(
            'SELECT * FROM admin_users WHERE email = ?',
            [mb_strtolower(trim($email))]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::first('SELECT * FROM admin_users WHERE id = ?', [$id]);
    }

    /** Crée le compte, ou remet simplement son mot de passe à jour s'il existe. */
    public function upsert(string $email, string $password, ?string $name = null): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $email = mb_strtolower(trim($email));

        if ($this->findByEmail($email) !== null) {
            // Le nom n'est mis à jour que s'il est fourni : un appel qui ne
            // sert qu'à changer le mot de passe ne doit pas effacer le nom
            // saisi depuis l'administration.
            if ($name !== null) {
                Database::run(
                    'UPDATE admin_users SET password_hash = ?, name = ? WHERE email = ?',
                    [$hash, $name, $email]
                );
            } else {
                Database::run('UPDATE admin_users SET password_hash = ? WHERE email = ?', [$hash, $email]);
            }

            return;
        }

        Database::run(
            'INSERT INTO admin_users (email, password_hash, name) VALUES (?, ?, ?)',
            [$email, $hash, $name]
        );
    }
}
