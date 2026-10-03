<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CategoryRepository;
use Bouge\Repository\ProductRepository;
use Bouge\Support\Config;
use Bouge\Support\Usage;

/**
 * `robots.txt` et `sitemap.xml`.
 *
 * Fabriqués à la volée plutôt que déposés dans `public/` : un fichier figé
 * vieillirait au premier produit ajouté, et il faudrait penser à le
 * régénérer. Ici le plan du site dit toujours ce que contient le catalogue.
 *
 * Les deux sont servis par PHP, ce qui coûte une requête de base de données
 * aux robots — à raison d'une visite par jour et par moteur, l'économie d'un
 * fichier statique ne vaut pas l'oubli de mise à jour.
 */
final class SitemapController
{
    public function robots(): string
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $site = $this->site();

        $lignes = [
            'User-agent: *',
            // Rien à indexer derrière ces chemins : ce sont des gestes, pas
            // des pages, et certains agissent sur une session.
            'Disallow: /admin',
            'Disallow: /compte',
            'Disallow: /panier',
            'Disallow: /commande',
            'Disallow: /webhook',
            'Disallow: /recherche',
            '',
        ];

        if ($site !== '') {
            $lignes[] = 'Sitemap: ' . $site . '/sitemap.xml';
        }

        return implode("\n", $lignes) . "\n";
    }

    public function sitemap(): string
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $site = $this->site();

        if ($site === '') {
            // Sans `site_url`, les adresses seraient relatives — un plan de
            // site relatif n'a aucun sens pour un moteur.
            http_response_code(503);

            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
        }

        $urls = [
            ['/', '1.0', 'daily'],
            ['/boutique', '0.9', 'daily'],
            ['/livraison', '0.4', 'monthly'],
            ['/cgv', '0.2', 'yearly'],
            ['/mentions-legales', '0.2', 'yearly'],
        ];

        foreach ((new CategoryRepository())->all() as $categorie) {
            $urls[] = ['/boutique/' . $categorie['slug'], '0.8', 'weekly'];
        }

        foreach (array_keys(Usage::all()) as $slug) {
            $urls[] = ['/usage/' . $slug, '0.7', 'weekly'];
        }

        // Les brouillons n'ont pas d'adresse publique : les lister enverrait
        // les moteurs sur des 404.
        foreach ((new ProductRepository())->published() as $produit) {
            $urls[] = ['/produit/' . $produit['slug'], '0.6', 'weekly', $produit['updated_at'] ?? null];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            [$chemin, $priorite, $frequence] = $url;
            $maj = $url[3] ?? null;

            $xml .= "  <url>\n"
                . '    <loc>' . htmlspecialchars($site . $chemin, ENT_XML1) . "</loc>\n"
                . ($maj !== null ? '    <lastmod>' . date('Y-m-d', strtotime((string) $maj)) . "</lastmod>\n" : '')
                . '    <changefreq>' . $frequence . "</changefreq>\n"
                . '    <priority>' . $priorite . "</priority>\n"
                . "  </url>\n";
        }

        return $xml . '</urlset>' . "\n";
    }

    private function site(): string
    {
        return rtrim((string) Config::get('site_url', ''), '/');
    }
}
