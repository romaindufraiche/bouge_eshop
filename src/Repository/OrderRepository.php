<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Shipping\ShippingLabel;
use Bouge\Support\Database;
use Bouge\Support\Status;
use PDO;

/**
 * Accès aux commandes.
 */
final class OrderRepository
{
    /**
     * Crée la commande et ses lignes dans une seule transaction : une
     * commande sans ses articles n'aurait aucun sens.
     *
     * @param array<string, mixed>             $order
     * @param array<int, array<string, mixed>> $items
     */
    public function create(array $order, array $items): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $columns = array_keys($order);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            Database::run(
                'INSERT INTO orders (' . implode(', ', $columns) . ") VALUES ({$placeholders})",
                array_values($order)
            );

            $orderId = (int) $pdo->lastInsertId();

            $statement = $pdo->prepare(
                'INSERT INTO order_items
                   (order_id, product_id, variant_id, product_name, variant_label,
                    image_url, unit_price_cents, vat_rate_bp, weight_grams, quantity,
                    line_total_cents)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $statement->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'],
                    $item['product_name'],
                    $item['variant_label'],
                    $item['image_url'],
                    $item['unit_price_cents'],
                    $item['vat_rate_bp'] ?? 2000,
                    $item['weight_grams'],
                    $item['quantity'],
                    $item['line_total_cents'],
                ]);
            }

            $pdo->commit();

            return $orderId;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    public function attachStripeSession(int $orderId, string $sessionId): void
    {
        Database::run('UPDATE orders SET stripe_session_id = ? WHERE id = ?', [$sessionId, $orderId]);
    }

    /** @return array<string, mixed>|null */
    public function findByStripeSession(string $sessionId): ?array
    {
        $order = Database::first('SELECT * FROM orders WHERE stripe_session_id = ?', [$sessionId]);

        return $order === null ? null : $this->withItems($order);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $order = Database::first(
            'SELECT o.*, pp.name AS pickup_name, pp.address_line1 AS pickup_address,
                    pp.postal_code AS pickup_postal_code, pp.city AS pickup_city,
                    pp.hours AS pickup_hours
             FROM orders o
             LEFT JOIN pickup_points pp ON pp.id = o.pickup_point_id
             WHERE o.id = ?',
            [$id]
        );

        return $order === null ? null : $this->withItems($order);
    }

    /**
     * Liste de l'administration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(string $status = '', string $fulfilment = '', string $search = ''): array
    {
        $sql = 'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
                FROM orders o WHERE 1 = 1';
        $params = [];

        // Une référence, un nom, une adresse électronique ou un numéro de
        // suivi : c'est avec l'un des quatre qu'un client se présente au
        // téléphone, et il n'y avait aucun moyen de retrouver sa commande
        // autrement qu'en la cherchant à l'œil dans la liste.
        $search = trim($search);

        if ($search !== '') {
            $sql .= ' AND (o.reference LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ?
                           OR o.tracking_number LIKE ?)';
            $motif = '%' . $search . '%';
            array_push($params, $motif, $motif, $motif, $motif);
        }

        if ($status !== '' && array_key_exists($status, Status::orderStatuses())) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        } elseif ($search !== '') {
            // Quand on cherche une commande précise, on la veut même si elle
            // n'a jamais été payée : c'est souvent justement pour ça qu'on la
            // cherche.
            $sql .= '';
        } else {
            // Par défaut on masque les commandes jamais payées : ce sont des
            // paniers abandonnés au moment du paiement, pas des commandes.
            $sql .= ' AND o.status <> ?';
            $params[] = Status::ORDER_PENDING;
        }

        if (in_array($fulfilment, [Status::DELIVERY, Status::PICKUP], true)) {
            $sql .= ' AND o.fulfilment = ?';
            $params[] = $fulfilment;
        }

        $sql .= ' ORDER BY o.created_at DESC LIMIT 200';

        return Database::all($sql, $params);
    }

    public function updateStatus(int $id, string $status): void
    {
        Database::run('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * Transporteur et numéro de suivi, saisis à l'expédition. La date
     * d'expédition est posée en même temps : c'est elle qui fait foi pour le
     * client, pas la date du changement de statut.
     */
    public function updateTracking(int $id, ?string $carrier, ?string $number): void
    {
        Database::run(
            'UPDATE orders
             SET tracking_carrier = ?, tracking_number = ?,
                 shipped_at = CASE WHEN ? IS NULL THEN NULL ELSE COALESCE(shipped_at, NOW()) END
             WHERE id = ?',
            [$carrier, $number, $number, $id]
        );
    }

    /**
     * Enregistre l'étiquette achetée auprès du transporteur, et le suivi qui
     * vient avec. La commande passe à « Expédiée » : acheter l'étiquette est
     * l'acte qui engage l'envoi, inutile de le redire en deux clics.
     */
    public function attachLabel(int $id, ShippingLabel $label): void
    {
        Database::run(
            'UPDATE orders
             SET label_url = ?, label_reference = ?,
                 tracking_carrier = ?, tracking_number = ?,
                 shipped_at = COALESCE(shipped_at, NOW()),
                 status = ?
             WHERE id = ?',
            [
                $label->url,
                $label->reference,
                mb_substr($label->carrier, 0, 60),
                mb_substr($label->trackingNumber, 0, 80),
                Status::ORDER_SHIPPED,
                $id,
            ]
        );
    }

    public function updateNote(int $id, ?string $note): void
    {
        Database::run('UPDATE orders SET admin_note = ? WHERE id = ?', [$note, $id]);
    }

    /**
     * Marque la commande payée et décompte le stock, une seule fois.
     *
     * Stripe rejoue parfois le même événement : sans le garde-fou sur le
     * statut, le stock serait décompté deux fois. Le tout est en transaction
     * pour qu'un incident au milieu ne laisse pas une commande payée avec un
     * stock à moitié décompté.
     */
    public function markPaid(int $orderId, ?string $paymentIntentId): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // SELECT ... FOR UPDATE verrouille la ligne : deux appels
            // simultanés du webhook ne peuvent pas passer le test tous les deux.
            $statement = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$orderId]);
            $status = $statement->fetchColumn();

            if ($status === false || $status !== Status::ORDER_PENDING) {
                $pdo->commit();

                return false;
            }

            Database::run(
                'UPDATE orders SET status = ?, paid_at = NOW(), stripe_payment_intent_id = ?
                 WHERE id = ?',
                [Status::ORDER_PAID, $paymentIntentId, $orderId]
            );

            $items = Database::all(
                'SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?',
                [$orderId]
            );

            foreach ($items as $item) {
                // GREATEST(0, ...) : le stock ne descend jamais sous zéro, un
                // compteur négatif serait incompréhensible dans l'admin.
                if ($item['variant_id'] !== null) {
                    Database::run(
                        'UPDATE product_variants SET stock = GREATEST(0, stock - ?) WHERE id = ?',
                        [(int) $item['quantity'], (int) $item['variant_id']]
                    );
                } elseif ($item['product_id'] !== null) {
                    Database::run(
                        'UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?',
                        [(int) $item['quantity'], (int) $item['product_id']]
                    );
                }
            }

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /** Annule une commande encore en attente, sans toucher à une commande payée. */
    public function cancelIfPending(int $orderId): void
    {
        Database::run(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [Status::ORDER_CANCELLED, $orderId, Status::ORDER_PENDING]
        );
    }

    /**
     * Retrouve une commande par sa référence et son jeton de suivi.
     *
     * C'est le chemin des commandes passées sans compte : le jeton tient lieu
     * de preuve. `hash_equals` compare en temps constant — sans lui, le temps
     * de réponse laisserait deviner le jeton caractère par caractère.
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $reference, string $jeton): ?array
    {
        if (strlen($jeton) !== 32) {
            return null;
        }

        $order = Database::first(
            'SELECT o.*, pp.name AS pickup_name, pp.address_line1 AS pickup_address,
                    pp.postal_code AS pickup_postal_code, pp.city AS pickup_city,
                    pp.hours AS pickup_hours
             FROM orders o
             LEFT JOIN pickup_points pp ON pp.id = o.pickup_point_id
             WHERE o.reference = ?',
            [$reference]
        );

        if ($order === null
            || $order['tracking_token'] === null
            || !hash_equals((string) $order['tracking_token'], $jeton)
        ) {
            return null;
        }

        return $this->withItems($order);
    }

    /** Référence lisible au téléphone : sans I, O, 0 ni 1, qui se confondent. */
    public static function generateReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $suffix = '';

        for ($i = 0; $i < 6; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'BG-' . $suffix;
    }

    /**
     * Le jeton qui ouvre la fiche de commande sans compte.
     *
     * Seize octets tirés au hasard : assez pour qu'on ne le devine pas, assez
     * court pour tenir dans une adresse sans la rendre illisible.
     */
    public static function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** @return array<int, array<string, mixed>> */
    public function dashboardCounts(): array
    {
        return [
            'to_prepare' => (int) Database::run(
                'SELECT COUNT(*) FROM orders WHERE status IN (?, ?)',
                [Status::ORDER_PAID, Status::ORDER_PREPARING]
            )->fetchColumn(),
            'paid_total' => (int) Database::run(
                'SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE paid_at IS NOT NULL AND status <> ?',
                [Status::ORDER_CANCELLED]
            )->fetchColumn(),
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function withItems(array $order): array
    {
        $order['items'] = Database::all(
            'SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC',
            [(int) $order['id']]
        );

        return $order;
    }
}
