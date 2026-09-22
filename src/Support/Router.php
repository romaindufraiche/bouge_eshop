<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Routeur.
 *
 * Assez pour une boutique : des chemins fixes et des segments nommés du type
 * `/produit/{slug}`. Pas de groupes, pas de middlewares — les quelques
 * contrôles nécessaires (session administrateur, jeton CSRF) sont appelés
 * explicitement là où ils s'appliquent, ce qui se lit mieux qu'une pile de
 * filtres implicites.
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, params: array<int, string>, handler: callable|array}> */
    private array $routes = [];

    /** @param callable|array{0: class-string, 1: string} $handler */
    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param callable|array{0: class-string, 1: string} $handler */
    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $params = [];

        // `/produit/{slug}` devient `#^/produit/([^/]+)$#`
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '([^/]+)';
            },
            $pattern
        ) ?? $pattern;

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    /**
     * Cherche la route correspondante et exécute son gestionnaire.
     * Renvoie false si aucune ne correspond, à charge de l'appelant
     * d'afficher une page 404.
     */
    public function dispatch(string $method, string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        // On ignore un éventuel slash final, sauf pour la racine.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $arguments = array_combine($route['params'], $matches) ?: [];

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $handler = [new $class(), $action];
            }

            echo (string) call_user_func($handler, $arguments);

            return true;
        }

        return false;
    }
}
