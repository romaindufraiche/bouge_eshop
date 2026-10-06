<?php

declare(strict_types=1);

namespace Bouge\Repository;

use Bouge\Support\Database;
use Bouge\Support\Status;

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


    /**
     * Le fichier client : tous ceux qui ont acheté ou ouvert un compte.
     *
     * Deux populations se recoupent sans se confondre. Les titulaires d'un
     * compte, d'abord, qu'ils aient commandé ou non. Les acheteurs sans
     * compte, ensuite : la boutique n'impose pas l'inscription, et leurs
     * coordonnées ne vivent que sur leurs commandes. Un fichier bâti sur la
     * seule table `customers` passerait donc à côté d'une bonne partie des
     * clients — probablement la majorité.
     *
     * La clé de rapprochement est l'adresse électronique, en minuscules :
     * c'est elle qui identifie une personne des deux côtés, et c'est elle que
     * `claimOrders()` utilise déjà pour rattacher à un compte les commandes
     * passées avant son ouverture.
     *
     * Les paniers abandonnés au paiement et les commandes annulées sont hors
     * du compte : ni l'un ni l'autre n'est un achat. Quelqu'un qui n'aurait
     * que cela à son actif reste dans le fichier s'il a un compte, avec zéro
     * commande.
     *
     * Données personnelles : ce fichier en est un au sens du RGPD. Il sert la
     * gestion de la boutique, pas la revente ; toute prospection suppose le
     * consentement, que la boutique ne recueille pas aujourd'hui.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fichierClient(): array
    {
        // Ce que les commandes disent de chaque adresse : combien d'achats,
        // pour quel montant, du premier au dernier, et la dernière valeur
        // connue pour le nom, le téléphone et la ville.
        //
        // « Dernière valeur connue » et non « valeur de la dernière commande » :
        // un retrait en magasin ou une livraison en point relais ne porte
        // aucune adresse de client, et l'adresse serait vide pour quiconque a
        // commandé ainsi en dernier. GROUP_CONCAT ignorant les NULL, NULLIF
        // écarte les champs vides, l'ordre descendant met le plus récent en
        // tête, et SUBSTRING_INDEX en prend le premier.
        //
        // Le séparateur est 0x1f, le « séparateur d'unité » d'ASCII : il ne
        // peut apparaître ni dans un nom ni dans une ville, contrairement à la
        // virgule.
        $commandes = Database::all(
            'SELECT LOWER(email) AS email,
                    COUNT(*)      AS commandes,
                    SUM(total_cents) AS total_cents,
                    MIN(created_at)  AS premiere,
                    MAX(created_at)  AS derniere,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(customer_name, "") ORDER BY created_at DESC SEPARATOR 0x1f), 0x1f, 1) AS nom,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(phone, "") ORDER BY created_at DESC SEPARATOR 0x1f), 0x1f, 1) AS telephone,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(shipping_postal_code, "") ORDER BY created_at DESC SEPARATOR 0x1f), 0x1f, 1) AS code_postal,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(shipping_city, "") ORDER BY created_at DESC SEPARATOR 0x1f), 0x1f, 1) AS ville
               FROM orders
              WHERE status NOT IN (?, ?)
              GROUP BY LOWER(email)',
            [Status::ORDER_PENDING, Status::ORDER_CANCELLED]
        );

        $fichier = [];

        foreach ($commandes as $ligne) {
            $fichier[(string) $ligne['email']] = [
                'email'       => (string) $ligne['email'],
                'nom'         => (string) ($ligne['nom'] ?? ''),
                'telephone'   => (string) ($ligne['telephone'] ?? ''),
                'code_postal' => (string) ($ligne['code_postal'] ?? ''),
                'ville'       => (string) ($ligne['ville'] ?? ''),
                'compte'      => false,
                'inscrit_le'  => null,
                'derniere_visite' => null,
                'commandes'   => (int) $ligne['commandes'],
                'total_cents' => (int) $ligne['total_cents'],
                'premiere'    => (string) $ligne['premiere'],
                'derniere'    => (string) $ligne['derniere'],
            ];
        }

        // Les comptes ensuite : ils complètent une fiche existante ou en
        // créent une. Le nom et les coordonnées du compte l'emportent sur ceux
        // d'une commande — c'est ce que le client a saisi pour lui-même, et
        // c'est ce qu'il tient à jour.
        foreach (Database::all(
            'SELECT LOWER(email) AS email, name, phone, postal_code, city, created_at, last_login_at
               FROM customers'
        ) as $compte) {
            $email = (string) $compte['email'];

            $fiche = $fichier[$email] ?? [
                'email' => $email, 'nom' => '', 'telephone' => '',
                'code_postal' => '', 'ville' => '',
                'commandes' => 0, 'total_cents' => 0,
                'premiere' => null, 'derniere' => null,
            ];

            $fichier[$email] = $fiche;
            $fichier[$email]['nom'] = (string) $compte['name'];
            $fichier[$email]['compte'] = true;
            $fichier[$email]['inscrit_le'] = (string) $compte['created_at'];
            $fichier[$email]['derniere_visite'] = $compte['last_login_at'] === null
                ? null
                : (string) $compte['last_login_at'];

            foreach (['telephone' => 'phone', 'code_postal' => 'postal_code', 'ville' => 'city'] as $cle => $colonne) {
                if (($compte[$colonne] ?? '') !== '' && $compte[$colonne] !== null) {
                    $fichier[$email][$cle] = (string) $compte[$colonne];
                }
            }
        }

        // Les meilleurs clients en tête : c'est l'ordre dans lequel on lit un
        // fichier client, et celui qu'un tableur garde à l'ouverture.
        uasort($fichier, static fn (array $a, array $b): int
            => [$b['total_cents'], $b['commandes']] <=> [$a['total_cents'], $a['commandes']]);

        return array_values($fichier);
    }

    /** @param array<string, mixed> $data */
    /**
     * La dernière adresse de livraison connue de ce client, prise sur ses
     * commandes.
     *
     * Elle sert à préremplir des coordonnées encore vides : un compte créé
     * avant que le tunnel ne les enregistre, ou créé après coup sur une
     * adresse ayant déjà commandé, affichait un formulaire blanc alors que
     * l'adresse figurait sur chacune de ses commandes.
     *
     * @return array<string, string>|null
     */
    public function lastShippingAddress(int $id): ?array
    {
        return Database::first(
            "SELECT customer_name AS name, phone,
                    shipping_address_line1 AS address_line1,
                    shipping_address_line2 AS address_line2,
                    shipping_postal_code AS postal_code,
                    shipping_city AS city
             FROM orders
             WHERE customer_id = ?
               AND shipping_address_line1 IS NOT NULL
               AND shipping_address_line1 <> ''
             ORDER BY created_at DESC
             LIMIT 1",
            [$id]
        );
    }

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
