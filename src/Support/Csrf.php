<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Protection contre les requêtes forgées depuis un autre site.
 *
 * Chaque formulaire en POST porte un jeton lié à la session. Un site tiers
 * peut faire envoyer une requête par le navigateur du visiteur, mais il ne
 * peut pas lire ce jeton : la requête est donc rejetée.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }

        return $token;
    }

    /** Champ caché à placer dans chaque formulaire POST. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function isValid(?string $submitted): bool
    {
        $expected = Session::get(self::KEY);

        if (!is_string($expected) || $expected === '' || !is_string($submitted)) {
            return false;
        }

        // Comparaison à temps constant : une comparaison ordinaire s'arrête au
        // premier caractère différent et laisse deviner le jeton.
        return hash_equals($expected, $submitted);
    }
}
