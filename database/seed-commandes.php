<?php

declare(strict_types=1);

/**
 * Commandes de démonstration.
 *
 * Un écran de commandes vide ne dit rien : ni si les statuts se distinguent
 * au coup d'œil, ni si les trois modes de remise se lisent, ni ce que donne
 * une pastille de notification à deux chiffres. Ce script remplit la liste
 * de commandes plausibles, tirées du vrai catalogue.
 *
 *     php database/seed-commandes.php            # ajoute une douzaine de commandes
 *     php database/seed-commandes.php --vider    # efface les commandes existantes d'abord
 *
 * Outil de développement et de démonstration. Sur une boutique en service,
 * il n'a rien à faire : il écrirait de fausses ventes dans l'historique.
 *
 * Les commandes passent par le même chemin que les vraies — création en
 * attente, puis `markPaid` —, ce qui décompte le stock exactement comme un
 * paiement réel. Autrement la démonstration montrerait des ventes sans effet
 * sur les stocks, ce qui n'arrive jamais.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Repository\OrderRepository;
use Bouge\Support\Config;
use Bouge\Support\Database;
use Bouge\Support\Status;

$vider = in_array('--vider', $argv, true);

$commandes = new OrderRepository();

if ($vider) {
    // Les lignes partent en cascade avec leur commande (voir le schéma).
    Database::run('DELETE FROM orders');
    echo "✓ Commandes existantes effacées\n";
}

// --- Le catalogue dans lequel puiser ----------------------------------------

$produits = Database::all(
    "SELECT p.id, p.name, p.price_cents, p.weight_grams,
            (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY i.position LIMIT 1) AS image_url
     FROM products p
     WHERE p.status = ?
     ORDER BY RAND()",
    [Status::PRODUCT_PUBLISHED]
);

if (count($produits) < 3) {
    fwrite(STDERR, "Le catalogue est trop maigre : chargez-le d'abord (php database/seed.php).\n");
    exit(1);
}

$pointDeRetrait = Database::first('SELECT id FROM pickup_points ORDER BY id LIMIT 1');
$clients = Database::all('SELECT id, email, name FROM customers ORDER BY id');

// --- Les personnages ---------------------------------------------------------
// Des commandes qui se ressemblent toutes ne montrent rien. Celles-ci varient
// le mode de remise, le statut, le nombre d'articles et l'ancienneté.

$scenarios = [
    // [nom, courriel, mode, statut, articles, jours d'ancienneté, note]
    ['Camille Martin',  'camille@example.com',   Status::RELAY,    Status::ORDER_PAID,       2, 0,  null],
    ['Sacha Roy',       'sacha@example.com',     Status::DELIVERY, Status::ORDER_PAID,       1, 0,  null],
    ['Inès Bouchard',   'ines.b@example.com',    Status::PICKUP,   Status::ORDER_PAID,       3, 1,  null],
    ['Thomas Lefèvre',  'tlefevre@example.com',  Status::RELAY,    Status::ORDER_PREPARING,  1, 2,  'Demande un envoi groupé avec sa prochaine commande.'],
    ['Nadia Belkacem',  'nadia.bk@example.com',  Status::DELIVERY, Status::ORDER_PREPARING,  4, 3,  null],
    ['Hugo Marchand',   'hugo.m@example.com',    Status::RELAY,    Status::ORDER_SHIPPED,    2, 6,  null],
    ['Léa Fontaine',    'lea.fontaine@example.com', Status::DELIVERY, Status::ORDER_SHIPPED, 1, 8,  null],
    ['Yanis Cohen',     'yanis.c@example.com',   Status::PICKUP,   Status::ORDER_COLLECTED,  2, 11, null],
    ['Margaux Pires',   'margaux.p@example.com', Status::RELAY,    Status::ORDER_SHIPPED,    3, 14, null],
    ['Olivier Dantec',  'o.dantec@example.com',  Status::DELIVERY, Status::ORDER_CANCELLED,  1, 17, 'Annulée à la demande du client : taille indisponible.'],
    ['Farida Slimani',  'farida.s@example.com',  Status::PICKUP,   Status::ORDER_COLLECTED,  1, 21, null],
    ['Vincent Aubry',   'v.aubry@example.com',   Status::DELIVERY, Status::ORDER_PENDING,    2, 0,  null],
];

$relais = [
    ['Tabac Le Longchamp',  '12 avenue Marceau',       '92400', 'Courbevoie'],
    ['Presse de la Défense', '4 place Charras',        '92400', 'Courbevoie'],
    ['Épicerie du Marché',  '27 rue de Bezons',        '92400', 'Courbevoie'],
    ['Pressing Central',    '8 boulevard Saint-Denis', '92700', 'Colombes'],
];

$adresses = [
    ['14 rue des Lilas',        '92400', 'Courbevoie'],
    ['3 allée des Peupliers',   '92700', 'Colombes'],
    ['61 avenue de la Liberté', '92000', 'Nanterre'],
    ['9 rue Pasteur',           '75017', 'Paris'],
];

$fraisDomicile = (int) Config::shop('shipping.flat_rate_cents', 490);
$fraisRelais = (int) Config::shop('shipping.relay_cents', 390);
$franco = (int) Config::shop('shipping.free_above_cents', 6000);

$cree = 0;

foreach ($scenarios as $i => [$nom, $courriel, $mode, $statut, $nombre, $jours, $note]) {
    // --- Les articles ---------------------------------------------------------
    $lignes = [];
    $sousTotal = 0;

    for ($n = 0; $n < $nombre; $n++) {
        $produit = $produits[($i * 3 + $n) % count($produits)];
        $quantite = $n === 0 && $nombre === 1 ? random_int(1, 2) : 1;
        $prix = (int) $produit['price_cents'];

        $lignes[] = [
            'product_id'       => (int) $produit['id'],
            'variant_id'       => null,
            'product_name'     => (string) $produit['name'],
            'variant_label'    => null,
            'image_url'        => $produit['image_url'],
            'unit_price_cents' => $prix,
            'weight_grams'     => (int) ($produit['weight_grams'] ?? 0),
            'quantity'         => $quantite,
            'line_total_cents' => $prix * $quantite,
        ];

        $sousTotal += $prix * $quantite;
    }

    // --- Les frais de port ----------------------------------------------------
    $frais = match ($mode) {
        Status::PICKUP => 0,
        Status::RELAY  => $sousTotal >= $franco ? 0 : $fraisRelais,
        default        => $sousTotal >= $franco ? 0 : $fraisDomicile,
    };

    $date = (new DateTimeImmutable())->modify("-{$jours} days")->modify('-' . random_int(0, 600) . ' minutes');

    $commande = [
        'reference'      => OrderRepository::generateReference(),
        'email'          => $courriel,
        'customer_name'  => $nom,
        'phone'          => '06 ' . implode(' ', [random_int(10, 99), random_int(10, 99), random_int(10, 99), random_int(10, 99)]),
        // Toutes naissent en attente de paiement, comme les vraies : c'est
        // `markPaid` qui les fait avancer, et lui seul touche au stock.
        'status'         => Status::ORDER_PENDING,
        'fulfilment'     => $mode,
        'subtotal_cents' => $sousTotal,
        'shipping_cents' => $frais,
        'total_cents'    => $sousTotal + $frais,
        'admin_note'     => $note,
        'created_at'     => $date->format('Y-m-d H:i:s'),
    ];

    if ($mode === Status::DELIVERY) {
        [$rue, $cp, $ville] = $adresses[$i % count($adresses)];
        $commande += [
            'shipping_address_line1' => $rue,
            'shipping_postal_code'   => $cp,
            'shipping_city'          => $ville,
            'shipping_country'       => 'FR',
        ];
    } elseif ($mode === Status::RELAY) {
        [$enseigne, $rue, $cp, $ville] = $relais[$i % count($relais)];
        $commande += [
            'relay_code'        => 'DEMO' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'relay_operator'    => 'MONR',
            'relay_name'        => $enseigne,
            'relay_address'     => $rue,
            'relay_postal_code' => $cp,
            'relay_city'        => $ville,
        ];
    } elseif ($pointDeRetrait !== null) {
        $commande['pickup_point_id'] = (int) $pointDeRetrait['id'];
    }

    // Rattacher la commande au compte client quand l'adresse en a un : c'est
    // ce que fait le tunnel, et c'est ce qui fait apparaître l'historique
    // dans l'espace client.
    foreach ($clients as $client) {
        if (strcasecmp((string) $client['email'], $courriel) === 0) {
            $commande['customer_id'] = (int) $client['id'];
            break;
        }
    }

    $id = $commandes->create($commande, $lignes);

    // --- L'avancement ---------------------------------------------------------
    if ($statut !== Status::ORDER_PENDING) {
        // Le même chemin que le webhook Stripe : stock décompté, date de
        // paiement posée.
        $commandes->markPaid($id, 'pi_demo_' . bin2hex(random_bytes(8)));
        Database::run(
            'UPDATE orders SET paid_at = ? WHERE id = ?',
            [$date->modify('+2 minutes')->format('Y-m-d H:i:s'), $id]
        );
    }

    if ($statut !== Status::ORDER_PAID && $statut !== Status::ORDER_PENDING) {
        $commandes->updateStatus($id, $statut);
    }

    if ($statut === Status::ORDER_SHIPPED) {
        $commandes->updateTracking(
            $id,
            $mode === Status::RELAY ? 'Mondial Relay' : 'Colissimo',
            strtoupper(bin2hex(random_bytes(5)))
        );
        Database::run(
            'UPDATE orders SET shipped_at = ? WHERE id = ?',
            [$date->modify('+1 day')->format('Y-m-d H:i:s'), $id]
        );
    }

    // `create` pose created_at, mais markPaid et consorts touchent updated_at :
    // sans cette remise à l'heure, toutes les commandes paraîtraient modifiées
    // à la seconde près, et la liste perdrait son ordre chronologique.
    Database::run(
        'UPDATE orders SET created_at = ?, updated_at = ? WHERE id = ?',
        [$date->format('Y-m-d H:i:s'), $date->format('Y-m-d H:i:s'), $id]
    );

    $cree++;
}

printf("✓ %d commandes de démonstration créées\n", $cree);

$parStatut = Database::all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status ORDER BY n DESC');

foreach ($parStatut as $ligne) {
    // printf aligne sur les octets ; « Expédiée » en compte un de plus qu'il
    // n'affiche de lettres, et la colonne se décale. On cale à la main.
    $libelle = Status::orderLabel((string) $ligne['status']);
    echo '   ', $libelle, str_repeat(' ', max(1, 24 - mb_strlen($libelle))), (int) $ligne['n'], "\n";
}
