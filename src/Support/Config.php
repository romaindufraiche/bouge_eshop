<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;

/**
 * Accès à la configuration.
 *
 * Deux fichiers, séparés parce qu'ils n'ont pas le même public :
 *   - `config/config.php` : identifiants et clés, propre à chaque installation,
 *     jamais versionné ;
 *   - `config/shop.php` : réglages commerciaux, versionnés et identiques
 *     partout.
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $app = null;

    /** @var array<string, mixed>|null */
    private static ?array $shop = null;

    /**
     * Lit une valeur de `config/config.php`, en notation pointée :
     * `Config::get('db.host')`.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::$app ??= self::load('config.php', true);

        return self::dig(self::$app, $key, $default);
    }

    /** Lit une valeur de `config/shop.php`. */
    public static function shop(string $key, mixed $default = null): mixed
    {
        self::$shop ??= self::load('shop.php', false);

        return self::dig(self::$shop, $key, $default);
    }

    /** @return array<string, mixed> */
    private static function load(string $file, bool $required): array
    {
        $path = dirname(__DIR__, 2) . '/config/' . $file;

        if (!is_file($path)) {
            if (!$required) {
                return [];
            }

            throw new RuntimeException(
                "Le fichier config/{$file} est absent. Copiez config/config.example.php "
                . 'en config/config.php et renseignez vos identifiants.'
            );
        }

        /** @var array<string, mixed> $values */
        $values = require $path;

        return $values;
    }

    /** @param array<string, mixed> $values */
    private static function dig(array $values, string $key, mixed $default): mixed
    {
        $current = $values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
