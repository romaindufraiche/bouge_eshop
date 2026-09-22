<?php

declare(strict_types=1);

/**
 * Jeu de données de démonstration.
 *
 *   php database/seed.php
 *
 * Le script est ré-exécutable : il vide le catalogue et le recrée à
 * l'identique. Les COMMANDES ne sont jamais touchées — elles conservent les
 * libellés et les prix recopiés au moment de l'achat.
 *
 * Le compte administrateur est créé à partir des arguments, ou avec les
 * valeurs par défaut ci-dessous :
 *
 *   php database/seed.php contact@bouge.fr mot-de-passe
 */

use Bouge\Repository\AdminUserRepository;
use Bouge\Support\Database;
use Bouge\Support\Slug;
use Bouge\Support\Status;

require dirname(__DIR__) . '/src/autoload.php';

$adminEmail = $argv[1] ?? 'contact@bouge.fr';
$adminPassword = $argv[2] ?? 'bouge-dev-2026';

$pdo = Database::connection();

// --- Compte administrateur ----------------------------------------------------
(new AdminUserRepository())->upsert($adminEmail, $adminPassword, 'Administration BOUGE.');
echo "✓ Compte admin : {$adminEmail}\n";

// --- Catalogue ------------------------------------------------------------------
// On repart d'un catalogue vide. Les clés étrangères des lignes de commande
// passent à NULL, la commande reste lisible grâce aux libellés recopiés.
Database::run('SET FOREIGN_KEY_CHECKS = 0');
Database::run('DELETE FROM product_variants');
Database::run('DELETE FROM product_images');
Database::run('DELETE FROM products');
Database::run('DELETE FROM categories');
Database::run('SET FOREIGN_KEY_CHECKS = 1');

$plusTard = static fn (int $jours): string => date('Y-m-d', strtotime("+{$jours} days"));

$catalogue = [
    [
        'name' => 'Bonnets',
        'description' => "Silicone ou tissu, pour l'entraînement comme pour la compétition.",
        'image' => '/assets/images/demo/bonnets.svg',
        'products' => [
            [
                'name' => 'Bonnet silicone uni',
                'description' => "Silicone épais, sans couture. Il tient en place sur les virages et ne tire pas les cheveux à l'enfilage. Taille unique adulte.",
                'price' => 1490, 'sale' => 1190, 'sale_ends' => $plusTard(21),
                'in_store' => true,
                'meta' => 'Bonnet de bain en silicone sans couture, taille unique adulte. Tient en place à chaque virage.',
                'variants' => [['color' => 'Noir', 'stock' => 40], ['color' => 'Blanc', 'stock' => 25], ['color' => 'Bleu', 'stock' => 18], ['color' => 'Rouge', 'stock' => 12]],
            ],
            [
                'name' => 'Bonnet tissu maille',
                'description' => "Polyester maillé, plus souple que le silicone. Moins étanche, mais confortable sur les longues séances. Se sèche en quelques minutes.",
                'price' => 1990,
                'meta' => 'Bonnet de bain en tissu polyester maillé, souple et respirant pour les longues séances.',
                'variants' => [['color' => 'Noir', 'stock' => 22], ['color' => 'Marine', 'stock' => 15]],
            ],
            [
                'name' => 'Bonnet longue chevelure',
                'description' => "Volume intérieur augmenté pour les cheveux longs ou attachés. Silicone souple, bords renforcés.",
                'price' => 1790,
                'meta' => 'Bonnet de bain silicone à volume augmenté, conçu pour les cheveux longs.',
                'variants' => [['color' => 'Noir', 'stock' => 20], ['color' => 'Violet', 'stock' => 14]],
            ],
        ],
    ],
    [
        'name' => 'Lunettes',
        'description' => 'Du créneau quotidien au départ plongé.',
        'image' => '/assets/images/demo/lunettes.svg',
        'products' => [
            [
                'name' => "Lunettes d'entraînement",
                'description' => "Joints en silicone souple, champ de vision large, traitement anti-buée. Le modèle à prendre si vous nagez plusieurs fois par semaine. Pont nasal interchangeable, trois tailles fournies.",
                'price' => 2490, 'in_store' => true,
                'meta' => "Lunettes de natation d'entraînement, joints silicone souple et traitement anti-buée.",
                'variants' => [['color' => 'Transparent', 'stock' => 30], ['color' => 'Fumé', 'stock' => 26], ['color' => 'Bleu', 'stock' => 19]],
            ],
            [
                'name' => 'Lunettes miroir compétition',
                'description' => "Profil bas, joints fins, verres miroir pour le bassin extérieur. Elles marquent le contour des yeux : à réserver aux séries et aux courses, pas aux deux heures d'entraînement.",
                'price' => 3990,
                'meta' => 'Lunettes de natation compétition à verres miroir et profil bas, pour bassin extérieur.',
                'variants' => [['color' => 'Argent', 'stock' => 12], ['color' => 'Or', 'stock' => 8]],
            ],
            [
                'name' => 'Lunettes junior',
                'description' => "Format réduit pour les 6-12 ans. Sangle double, boucles à réglage rapide que l'enfant manipule seul.",
                'price' => 1890,
                'meta' => 'Lunettes de natation junior 6-12 ans, sangle double et réglage rapide.',
                'variants' => [['color' => 'Bleu', 'stock' => 24], ['color' => 'Rose', 'stock' => 21]],
            ],
        ],
    ],
    [
        'name' => 'Accessoires',
        'description' => 'Le matériel qui structure une séance.',
        'image' => '/assets/images/demo/accessoires.svg',
        'products' => [
            [
                'name' => 'Pull-buoy',
                'description' => "Mousse EVA haute densité, forme sablier. Bloque les jambes et reporte le travail sur les bras. Se coince entre les cuisses ou les chevilles selon l'exercice.",
                'price' => 2190, 'stock' => 35,
                'meta' => 'Pull-buoy en mousse EVA haute densité pour le travail des bras en natation.',
            ],
            [
                'name' => 'Plaquettes de traction',
                'description' => "Surface perforée pour sentir l'appui sans forcer sur l'épaule. Sangles silicone amovibles. Commencez par la taille en dessous de votre intuition.",
                'price' => 2690,
                'meta' => 'Plaquettes de natation perforées avec sangles silicone, pour le travail de traction.',
                'variants' => [['size' => 'S', 'stock' => 14], ['size' => 'M', 'stock' => 20], ['size' => 'L', 'stock' => 11]],
            ],
            [
                'name' => 'Pince-nez',
                'description' => "Silicone souple sur armature métal, se déforme puis reprend sa forme. Indispensable en dos et en travail de coulée.",
                'price' => 690, 'stock' => 60,
                'meta' => 'Pince-nez de natation en silicone souple sur armature métal.',
            ],
            [
                'name' => 'Sac filet',
                'description' => "Maille large : le matériel sèche dedans, l'eau s'évacue. Contient une paire de palmes, un pull-buoy et des plaquettes.",
                'price' => 1690, 'stock' => 28,
                'meta' => 'Sac filet à maille large pour transporter et faire sécher le matériel de natation.',
            ],
        ],
    ],
    [
        'name' => 'Vêtements',
        'description' => 'Maillots et textile résistants au chlore.',
        'image' => '/assets/images/demo/vetements.svg',
        'products' => [
            [
                'name' => "Maillot d'entraînement femme",
                'description' => "Polyester résistant au chlore, dos nageur. Il garde sa tenue après des centaines de séances là où un maillot classique se détend en un trimestre.",
                'price' => 4990,
                'meta' => 'Maillot de bain une pièce femme en polyester résistant au chlore, dos nageur.',
                'variants' => [['size' => '36', 'stock' => 8], ['size' => '38', 'stock' => 12], ['size' => '40', 'stock' => 10], ['size' => '42', 'stock' => 7], ['size' => '44', 'stock' => 5]],
            ],
            [
                'name' => 'Jammer homme',
                'description' => "Coupe mi-cuisse, taille élastiquée avec cordon. Polyester résistant au chlore, coutures plates.",
                'price' => 4490, 'sale' => 3590, 'sale_ends' => $plusTard(14),
                'meta' => 'Jammer de natation homme en polyester résistant au chlore, coupe mi-cuisse.',
                'variants' => [['size' => '1 (28)', 'stock' => 6], ['size' => '2 (30)', 'stock' => 11], ['size' => '3 (32)', 'stock' => 9], ['size' => '4 (34)', 'stock' => 4]],
            ],
            [
                'name' => 'Serviette microfibre',
                'description' => "Absorbe trois fois son poids, sèche en une heure et tient dans une poche de sac. 80 × 130 cm, étui fourni.",
                'price' => 2990, 'stock' => 30,
                'meta' => 'Serviette de natation en microfibre 80 × 130 cm, séchage rapide, étui fourni.',
            ],
        ],
    ],
    [
        'name' => 'Livre',
        'description' => 'Le livre de Melvin Maillot, fondateur de la marque.',
        'image' => '/assets/images/demo/livre.svg',
        'products' => [
            [
                // Seul article réel du catalogue : il n'est pas vendu ici mais
                // par la Fnac, et disponible à la boutique.
                //
                // La description reste à compléter : la fiche du revendeur
                // refuse la lecture automatisée et la page de l'éditeur ne
                // publie ni résumé ni prix. Rien n'a été inventé.
                'name' => 'Corps et esprit',
                'description' => "Le livre de Melvin Maillot, fondateur de BOUGE.\n\nRésumé à compléter depuis l'administration : ni la fiche du revendeur ni celle de l'éditeur ne le publient.",
                // Prix non affiché pour un produit vendu ailleurs : celui du
                // revendeur fait foi et peut changer sans que nous le sachions.
                'price' => 0,
                'featured' => true,
                'in_store' => true,
                'external_url' => 'https://www.fnac.com/a23070255/Melvin-Maillot-Corps-et-esprit',
                'external_label' => 'la Fnac',
                'meta' => 'Corps et esprit, le livre de Melvin Maillot. Vendu par la Fnac et disponible en magasin.',
            ],
        ],
    ],
];

$categoryCount = 0;
$productCount = 0;

foreach ($catalogue as $position => $category) {
    Database::run(
        'INSERT INTO categories (name, slug, description, position) VALUES (?, ?, ?, ?)',
        [$category['name'], Slug::make($category['name']), $category['description'], $position]
    );
    $categoryId = (int) $pdo->lastInsertId();
    $categoryCount++;

    foreach ($category['products'] as $product) {
        Database::run(
            'INSERT INTO products
               (name, slug, description, category_id, price_cents, sale_price_cents,
                sale_ends_at, status, stock, featured, external_url, external_label,
                available_in_store, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $product['name'],
                Slug::make($product['name']),
                $product['description'],
                $categoryId,
                $product['price'],
                $product['sale'] ?? null,
                $product['sale_ends'] ?? null,
                Status::PRODUCT_PUBLISHED,
                $product['stock'] ?? 0,
                !empty($product['featured']) ? 1 : 0,
                $product['external_url'] ?? null,
                $product['external_label'] ?? null,
                !empty($product['in_store']) ? 1 : 0,
                $product['meta'],
            ]
        );
        $productId = (int) $pdo->lastInsertId();
        $productCount++;

        Database::run(
            'INSERT INTO product_images (product_id, url, alt, position) VALUES (?, ?, ?, 0)',
            [$productId, $category['image'], "{$product['name']} — {$category['name']} BOUGE."]
        );

        foreach ($product['variants'] ?? [] as $index => $variant) {
            Database::run(
                'INSERT INTO product_variants (product_id, size, color, stock, position)
                 VALUES (?, ?, ?, ?, ?)',
                [$productId, $variant['size'] ?? null, $variant['color'] ?? null, $variant['stock'], $index]
            );
        }
    }
}

echo "✓ Catalogue : {$categoryCount} catégories, {$productCount} produits\n";

// --- Point de retrait -----------------------------------------------------------
$existing = (int) Database::run('SELECT COUNT(*) FROM pickup_points')->fetchColumn();

if ($existing === 0) {
    Database::run(
        'INSERT INTO pickup_points (name, address_line1, postal_code, city, hours, position)
         VALUES (?, ?, ?, ?, ?, 0)',
        ['Boutique BOUGE.', '12 rue de la Piscine', '92400', 'Courbevoie', 'Du mardi au samedi, 10h – 19h']
    );
    echo "✓ Point de retrait de démonstration créé\n";
}
