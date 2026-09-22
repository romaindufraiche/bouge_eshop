<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\OrderRepository;
use Bouge\Support\Auth;
use Bouge\Support\Database;
use Bouge\Support\Status;
use Bouge\Support\View;

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
}
