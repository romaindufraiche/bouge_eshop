<?php

declare(strict_types=1);

use Bouge\Support\Config;

/**
 * Fonctions disponibles partout, et surtout dans les gabarits.
 */

if (!function_exists('e')) {
    /**
     * Échappement HTML.
     *
     * TOUTE valeur affichée dans un gabarit passe par cette fonction. Sans
     * elle, un nom de produit contenant du HTML serait interprété par le
     * navigateur : c'est la porte d'entrée classique des injections de script.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Adresse absolue d'une page du site. */
    function url(string $path = '/'): string
    {
        $base = rtrim((string) Config::get('site_url', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Adresse d'un fichier statique, suffixée par sa date de modification :
     * le navigateur recharge la feuille de style dès qu'elle change, sans
     * qu'on ait à vider son cache à la main.
     */
    function asset(string $path): string
    {
        $relative = '/' . ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public' . $relative;

        $version = is_file($file) ? '?v=' . filemtime($file) : '';

        return $relative . $version;
    }
}

if (!function_exists('redirect')) {
    /** Redirection puis arrêt : rien ne doit s'exécuter après. */
    function redirect(string $path, int $status = 303): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : $path), true, $status);
        exit;
    }
}

if (!function_exists('old')) {
    /**
     * Valeur précédemment saisie dans un formulaire, réaffichée après une
     * erreur de validation : sans cela l'utilisateur retape tout.
     *
     * @param array<string, mixed> $values
     */
    function old(array $values, string $key, mixed $default = ''): mixed
    {
        return $values[$key] ?? $default;
    }
}
