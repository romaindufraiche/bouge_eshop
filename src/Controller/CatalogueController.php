<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CategoryRepository;
use Bouge\Repository\ProductRepository;
use Bouge\Support\Usage;
use Bouge\Support\View;

/**
 * Catalogue et ses entrées.
 *
 * Trois façons d'arriver aux produits, comme chez les marques de natation :
 * par type (les catégories), par usage (entraînement, compétition…) et par
 * raccourci (nouveautés, promotions, disponible en magasin). Chacune a sa
 * propre adresse, partageable et indexable ; les filtres qui se cumulent
 * passent en paramètres d'URL.
 */
final class CatalogueController
{
    /** Un produit est « nouveau » pendant deux mois. */
    private const NEW_DAYS = 60;

    /** Tout le matériel, avec filtres et tri. */
    public function index(): string
    {
        $filtres = $this->filtresDepuisUrl();

        return $this->afficher(
            titre: 'Tout le matériel de natation',
            description: "L'ensemble du catalogue BOUGE Club : bonnets, lunettes, accessoires "
                . 'et maillots de natation. Livraison en France ou retrait sur place.',
            canonical: '/boutique',
            filtres: $filtres,
        );
    }

    /** Une catégorie : bonnets, lunettes… */
    public function category(array $params): string
    {
        $category = (new CategoryRepository())->findBySlug($params['slug']);

        if ($category === null) {
            return $this->introuvable('Catégorie introuvable');
        }

        $filtres = $this->filtresDepuisUrl();
        $filtres['category_id'] = (int) $category['id'];

        return $this->afficher(
            titre: $category['meta_title'] ?: $category['name'] . ' de natation',
            description: $category['meta_description']
                ?: ($category['description'] ?: 'Tous nos produits de la catégorie ' . $category['name'] . '.'),
            canonical: '/boutique/' . $category['slug'],
            filtres: $filtres,
            category: $category,
        );
    }

    /** Un usage : entraînement, compétition, loisir… */
    public function usage(array $params): string
    {
        $slug = $params['slug'];

        if (!Usage::exists($slug)) {
            return $this->introuvable('Page introuvable');
        }

        $filtres = $this->filtresDepuisUrl();
        $filtres['usage'] = $slug;

        $libelle = Usage::label($slug);

        return $this->afficher(
            titre: $libelle . ' — le matériel',
            description: Usage::descriptions()[$slug] ?? ('Le matériel de natation pour ' . mb_strtolower($libelle) . '.'),
            canonical: '/usage/' . $slug,
            filtres: $filtres,
            usage: $slug,
        );
    }

    /** Raccourcis : nouveautés, promotions, disponible en magasin. */
    public function shortcut(array $params): string
    {
        $raccourcis = [
            'nouveautes'  => ['Nouveautés', 'Les dernières arrivées à la boutique.', ['new_since_days' => self::NEW_DAYS]],
            'promotions'  => ['Promotions', 'Les prix en baisse, tant que la promotion dure.', ['on_sale' => true]],
            'en-magasin'  => ['Disponible en magasin', 'À voir, essayer et emporter au concept store, sans frais de port.', ['in_store' => true]],
        ];

        $slug = $params['slug'];

        if (!isset($raccourcis[$slug])) {
            return $this->introuvable('Page introuvable');
        }

        [$titre, $description, $filtresRaccourci] = $raccourcis[$slug];

        return $this->afficher(
            titre: $titre,
            description: $description,
            canonical: '/boutique/selection/' . $slug,
            filtres: array_merge($this->filtresDepuisUrl(), $filtresRaccourci),
            shortcut: $slug,
            shortcutTitle: $titre,
            shortcutDescription: $description,
        );
    }

    /** Recherche par mot-clé. */
    public function search(): string
    {
        $terme = trim((string) ($_GET['q'] ?? ''));
        // Une recherche très longue ne ramènerait rien et alourdirait la
        // requête : on la borne.
        $terme = mb_substr($terme, 0, 80);

        $filtres = $this->filtresDepuisUrl();

        if ($terme !== '') {
            $filtres['search'] = $terme;
        }

        return $this->afficher(
            titre: $terme === '' ? 'Rechercher' : 'Recherche : ' . $terme,
            description: 'Rechercher un produit dans le catalogue BOUGE Club.',
            canonical: '/recherche',
            filtres: $filtres,
            search: $terme,
            // Une page de résultats n'a pas à être indexée : elle n'apporte
            // rien qu'une page de catégorie ne dise déjà.
            noindex: true,
        );
    }

    // --- Rouages -------------------------------------------------------------

    /**
     * Filtres communs lus dans l'URL, quelle que soit la page d'entrée.
     *
     * @return array<string, mixed>
     */
    private function filtresDepuisUrl(): array
    {
        $filtres = [];

        $usage = (string) ($_GET['usage'] ?? '');
        if (Usage::exists($usage)) {
            $filtres['usage'] = $usage;
        }

        $categorie = (string) ($_GET['categorie'] ?? '');
        if ($categorie !== '') {
            $trouvee = (new CategoryRepository())->findBySlug($categorie);
            if ($trouvee !== null) {
                $filtres['category_id'] = (int) $trouvee['id'];
            }
        }

        if (($_GET['dispo'] ?? '') === 'magasin') {
            $filtres['in_store'] = true;
        }

        if (($_GET['promo'] ?? '') === '1') {
            $filtres['on_sale'] = true;
        }

        return $filtres;
    }

    /** @param array<string, mixed> $filtres */
    private function afficher(
        string $titre,
        string $description,
        string $canonical,
        array $filtres,
        ?array $category = null,
        ?string $usage = null,
        ?string $shortcut = null,
        ?string $shortcutTitle = null,
        ?string $shortcutDescription = null,
        string $search = '',
        bool $noindex = false,
    ): string {
        $tri = (string) ($_GET['tri'] ?? 'nouveautes');
        if (!array_key_exists($tri, ProductRepository::SORTS)) {
            $tri = 'nouveautes';
        }

        return View::render('boutique/catalogue', [
            'title'       => $titre,
            'description' => $description,
            'canonical'   => $canonical,
            'noindex'     => $noindex,
            'products'    => (new ProductRepository())->browse($filtres, $tri),
            'categories'  => (new CategoryRepository())->all(),
            'category'    => $category,
            'usage'       => $usage ?? ($filtres['usage'] ?? null),
            'shortcut'    => $shortcut,
            'shortcutTitle'       => $shortcutTitle,
            'shortcutDescription' => $shortcutDescription,
            'search'      => $search,
            'tri'         => $tri,
            'filtres'     => $filtres,
        ]);
    }

    private function introuvable(string $titre): string
    {
        http_response_code(404);

        return View::render('boutique/404', ['title' => $titre, 'noindex' => true]);
    }
}
