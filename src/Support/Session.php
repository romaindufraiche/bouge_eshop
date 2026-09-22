<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Session PHP, configurée avant tout démarrage.
 *
 * Le cookie de session est inaccessible au JavaScript de la page (`httponly`)
 * et n'est pas transmis aux requêtes venues d'un autre site (`samesite`), ce
 * qui coupe une bonne partie des attaques par requête forgée.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (str_starts_with((string) Config::get('site_url', ''), 'https://'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('bouge_session');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Message affiché une seule fois, à la page suivante.
     * Sert aux confirmations après un enregistrement ou une suppression.
     */
    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $message;
    }

    public static function takeFlash(string $key): ?string
    {
        self::start();
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }

    /**
     * À appeler juste après une connexion réussie : un identifiant de session
     * obtenu avant l'authentification ne doit pas rester valable après.
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
