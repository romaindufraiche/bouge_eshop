<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Usages d'un produit : la question que se pose vraiment un nageur.
 *
 * C'est l'axe de navigation principal de Speedo et d'Arena, à côté du type
 * de produit : on ne cherche pas « un bonnet », on cherche « de quoi
 * s'entraîner ». Un produit peut servir à plusieurs usages — un bonnet en
 * silicone vaut pour l'entraînement comme pour la compétition.
 *
 * Les valeurs sont stockées dans une colonne VARCHAR, séparées par des
 * virgules et encadrées par des virgules (« ,entrainement,loisir, ») : la
 * recherche se fait alors avec un LIKE '%,entrainement,%' sans risque qu'un
 * usage en contienne un autre. Une table de liaison serait plus orthodoxe,
 * mais pour une liste fermée de cinq valeurs elle n'apporterait que des
 * jointures.
 */
final class Usage
{
    public const TRAINING = 'entrainement';
    public const COMPETITION = 'competition';
    public const LEISURE = 'loisir';
    public const OPEN_WATER = 'eau-libre';
    public const LEARNING = 'apprentissage';

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            self::TRAINING    => 'Entraînement',
            self::COMPETITION => 'Compétition',
            self::LEISURE     => 'Loisir et bien-être',
            self::OPEN_WATER  => 'Eau libre et triathlon',
            self::LEARNING    => 'Apprentissage',
        ];
    }

    /** Phrase d'accroche de la page d'un usage. */
    public static function descriptions(): array
    {
        return [
            self::TRAINING    => "Ce qui encaisse les séries, le chlore et les allers-retours au bassin.",
            self::COMPETITION => "Le matériel des jours de course : léger, rapide, sans rien qui dépasse.",
            self::LEISURE     => "Pour nager à son rythme, longer la ligne d'eau et sortir détendu.",
            self::OPEN_WATER  => "Lac, mer, triathlon : visibilité, tenue et confort sur la distance.",
            self::LEARNING    => "Pour les premières longueurs, sans se battre avec son matériel.",
        ];
    }

    public static function label(string $slug): string
    {
        return self::all()[$slug] ?? $slug;
    }

    public static function exists(string $slug): bool
    {
        return array_key_exists($slug, self::all());
    }

    /**
     * Transforme la valeur stockée en liste de slugs.
     *
     * @return array<int, string>
     */
    public static function toList(?string $stored): array
    {
        if ($stored === null || trim($stored, ' ,') === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $stored)),
            static fn (string $slug): bool => self::exists($slug)
        ));
    }

    /**
     * Prépare la valeur à stocker. Les virgules encadrantes permettent une
     * recherche exacte en SQL.
     *
     * @param array<int, string> $slugs
     */
    public static function toStorage(array $slugs): ?string
    {
        $retenus = array_values(array_unique(array_filter(
            $slugs,
            static fn (mixed $slug): bool => is_string($slug) && self::exists($slug)
        )));

        return $retenus === [] ? null : ',' . implode(',', $retenus) . ',';
    }
}
