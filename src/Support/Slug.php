<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Fabrication des identifiants d'URL.
 */
final class Slug
{
    /** « Bonnet silicone uni » devient « bonnet-silicone-uni ». */
    public static function make(string $input): string
    {
        // On retire les accents en décomposant les caractères puis en
        // supprimant les signes diacritiques.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        if ($ascii === false) {
            $ascii = $input;
        }

        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return substr($slug, 0, 80);
    }

    /**
     * Rend un slug unique en lui ajoutant un suffixe numérique si besoin :
     * « bonnet », « bonnet-2 », « bonnet-3 »…
     *
     * @param callable(string): bool $isTaken vrai si le slug est déjà pris par
     *                                        un AUTRE enregistrement
     */
    public static function unique(string $base, callable $isTaken): string
    {
        $root = self::make($base);
        if ($root === '') {
            $root = 'produit';
        }

        if (!$isTaken($root)) {
            return $root;
        }

        for ($suffix = 2; $suffix < 100; $suffix++) {
            $candidate = "{$root}-{$suffix}";
            if (!$isTaken($candidate)) {
                return $candidate;
            }
        }

        // Cas extrême : un suffixe temporel est toujours disponible.
        return $root . '-' . time();
    }
}
