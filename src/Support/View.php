<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;

/**
 * Rendu des gabarits.
 *
 * Des fichiers PHP simples, sans moteur de gabarits : il n'y a rien à
 * installer, rien à compiler, et n'importe quel développeur PHP les lit sans
 * apprentissage. En contrepartie, l'échappement est à notre charge — d'où la
 * fonction `e()` utilisée systématiquement.
 */
final class View
{
    /** @var array<string, mixed> Données partagées par tous les gabarits. */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Rend un gabarit dans une mise en page.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layout/shop'): string
    {
        $content = self::capture($template, $data);

        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** Rend un gabarit seul, sans mise en page (fragments, courriels). */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $path = dirname(__DIR__, 2) . '/templates/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("Gabarit introuvable : {$template}");
        }

        // Les données sont extraites en variables locales au gabarit.
        // EXTR_SKIP empêche qu'une clé écrase $path ou $data par mégarde.
        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
