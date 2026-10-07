<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\CustomerRepository;
use Bouge\Repository\OrderRepository;
use Bouge\Support\Auth;
use Bouge\Support\Database;
use Bouge\Support\Status;
use Bouge\Support\View;
use Bouge\Support\Xlsx;
use DateTimeImmutable;

final class DashboardController
{
    public function index(): string
    {
        Auth::require();

        $counts = (new OrderRepository())->dashboardCounts();

        return View::render('admin/tableau-de-bord', [
            'title'      => 'Tableau de bord',
            'published'  => (int) Database::run('SELECT COUNT(*) FROM products WHERE status = ?', [Status::PRODUCT_PUBLISHED])->fetchColumn(),
            'drafts'     => (int) Database::run('SELECT COUNT(*) FROM products WHERE status = ?', [Status::PRODUCT_DRAFT])->fetchColumn(),
            'categories' => (int) Database::run('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'toPrepare'  => $counts['to_prepare'],
            'paidTotal'  => $counts['paid_total'],
            'recent'     => Database::all(
                'SELECT id, reference, customer_name, status, total_cents, created_at
                 FROM orders WHERE status <> ? ORDER BY created_at DESC LIMIT 8',
                [Status::ORDER_PENDING]
            ),
            // Stocks faibles : déclinaisons et produits sans déclinaison.
            'lowStock' => Database::all(
                "SELECT p.id, p.name,
                        CONCAT_WS(' · ', NULLIF(v.size, ''), NULLIF(v.color, '')) AS variant_label,
                        v.stock
                 FROM product_variants v
                 JOIN products p ON p.id = v.product_id
                 WHERE v.stock <= 3 AND p.status = ?
                 UNION ALL
                 SELECT p.id, p.name, NULL AS variant_label, p.stock
                 FROM products p
                 WHERE p.stock <= 3 AND p.status = ?
                   AND NOT EXISTS (SELECT 1 FROM product_variants v2 WHERE v2.product_id = p.id)
                   AND (p.external_url IS NULL OR p.external_url = '')
                 ORDER BY stock ASC
                 LIMIT 10",
                [Status::PRODUCT_PUBLISHED, Status::PRODUCT_PUBLISHED]
            ),
        ], 'layout/admin');
    }

    /**
     * Le fichier client, en classeur Excel.
     *
     * Il part du tableau de bord et non d'une liste de clients : la boutique
     * n'en tient pas, parce qu'on peut commander sans compte et qu'une page
     * « Clients » laisserait croire le contraire. Le fichier, lui, réunit les
     * deux populations — voir CustomerRepository::fichierClient().
     *
     * Sans filtre à reprendre, contrairement aux deux autres exports : le
     * tableau de bord n'en porte aucun, et un fichier client s'emporte
     * entier.
     */
    public function exportCustomers(): string
    {
        Auth::require();

        $lignes = [];

        foreach ((new CustomerRepository())->fichierClient() as $client) {
            $total = $client['total_cents'] / 100;

            $lignes[] = [
                $client['nom'],
                $client['email'],
                $client['telephone'],
                $client['code_postal'],
                $client['ville'],
                $client['compte'] ? 'Oui' : 'Non',
                $client['commandes'],
                $total,
                // Le panier moyen n'est pas calculable sans commande : la
                // cellule reste vide plutôt que d'afficher un zéro, qui se
                // lirait comme « il achète pour rien ».
                $client['commandes'] > 0 ? round($total / $client['commandes'], 2) : null,
                self::dateOuRien($client['premiere']),
                self::dateOuRien($client['derniere']),
                self::dateOuRien($client['inscrit_le']),
                self::dateOuRien($client['derniere_visite'] ?? null),
            ];
        }

        return Xlsx::telecharger(
            [
                'Nom', 'Adresse électronique', 'Téléphone', 'Code postal', 'Ville',
                'Compte', 'Commandes', 'Total dépensé (€)', 'Panier moyen (€)',
                'Première commande', 'Dernière commande', 'Inscrit le', 'Dernière connexion',
            ],
            $lignes,
            'Clients au ' . date('d-m-Y'),
            'clients-bouge-club-' . date('Y-m-d') . '.xlsx',
            '/admin'
        );
    }

    /**
     * Une date de base en date de tableur, ou rien.
     *
     * Le classeur attend un objet date pour écrire une vraie date, triable et
     * formatable dans Excel. Une chaîne vide y tomberait en texte.
     */
    private static function dateOuRien(?string $valeur): ?DateTimeImmutable
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($valeur);
        } catch (\Throwable) {
            return null;
        }
    }
}
