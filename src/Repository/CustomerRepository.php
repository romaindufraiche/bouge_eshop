<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;

/**
 * Comptes clients.
 *
 * Le compte est facultatif : on peut commander sans. Il sert à retrouver son
 * panier d'un appareil à l'autre, à ne pas retaper son adresse et à suivre
 * ses commandes.
 */
final class CustomerRepository
{
    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return Database::first(
            'SELECT * FROM customers WHERE email = ?',
            [mb_strtolower(trim($email))]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::first('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public function create(string $email, string $password, string $name, ?string $phone = null): int
    {
        Database::run(
            'INSERT INTO customers (email, password_hash, name, phone) VALUES (?, ?, ?, ?)',
            [
                mb_strtolower(trim($email)),
                // PASSWORD_DEFAULT suit les recommandations de PHP : le jour où
                // l'algorithme par défaut change, les nouveaux comptes en
                // profitent sans modification du code.
                password_hash($password, PASSWORD_DEFAULT),
                $name,
                $phone,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function updatePassword(int $id, string $password): void
    {
        Database::run(
            'UPDATE customers SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(int $id, array $data): void
    {
        $assignments = implode(', ', array_map(
            static fn (string $colonne): string => "{$colonne} = ?",
            array_keys($data)
        ));

        Database::run(
            "UPDATE customers SET {$assignments} WHERE id = ?",
            [...array_values($data), $id]
        );
    }

    public function touchLogin(int $id): void
    {
        Database::run('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    // --- Panier conservé ------------------------------------------------------

    /**
     * Le panier est stocké tel quel : des identifiants et des quantités. Ni
     * libellé ni prix — ils sont relus en base à chaque affichage.
     *
     * @param array<int, array<string, mixed>> $lignes
     */
    public function saveCart(int $id, array $lignes): void
    {
        Database::run(
            'UPDATE customers SET cart = ? WHERE id = ?',
            [$lignes === [] ? null : json_encode($lignes, JSON_UNESCAPED_UNICODE), $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function loadCart(int $id): array
    {
        $client = $this->find($id);

        if ($client === null || $client['cart'] === null) {
            return [];
        }

        $lignes = json_decode((string) $client['cart'], true);

        return is_array($lignes) ? $lignes : [];
    }

    // --- Commandes du client ---------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function orders(int $customerId): array
    {
        return Database::all(
            'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
             FROM orders o
             WHERE o.customer_id = ? AND o.status <> ?
             ORDER BY o.created_at DESC
             LIMIT 100',
            [$customerId, \Bouge\Support\Status::ORDER_PENDING]
        );
    }

    /**
     * Une commande précise du client. Le filtre sur `customer_id` est le
     * contrôle d'accès : sans lui, changer la référence dans l'adresse
     * donnerait la commande du voisin.
     *
     * @return array<string, mixed>|null
     */
    public function order(int $customerId, string $reference): ?array
    {
        $commande = Database::first(
            'SELECT o.*, pp.name AS pickup_name, pp.address_line1 AS pickup_address,
                    pp.postal_code AS pickup_postal_code, pp.city AS pickup_city,
                    pp.hours AS pickup_hours
             FROM orders o
             LEFT JOIN pickup_points pp ON pp.id = o.pickup_point_id
             WHERE o.customer_id = ? AND o.reference = ?',
            [$customerId, $reference]
        );

        if ($commande === null) {
            return null;
        }

        $commande['items'] = Database::all(
            'SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC',
            [(int) $commande['id']]
        );

        return $commande;
    }

    /** Rattache au compte les commandes passées avec la même adresse avant l'inscription. */
    public function claimOrders(int $customerId, string $email): int
    {
        $statement = Database::run(
            'UPDATE orders SET customer_id = ? WHERE customer_id IS NULL AND email = ?',
            [$customerId, mb_strtolower(trim($email))]
        );

        return $statement->rowCount();
    }
}
