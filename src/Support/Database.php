<?php

declare(strict_types=1);

namespace Bouge\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Connexion à la base.
 *
 * Une seule connexion par requête HTTP, ouverte à la première utilisation.
 * PDO est configuré pour lever des exceptions plutôt que renvoyer `false`
 * silencieusement, et pour ne pas émuler les requêtes préparées : c'est le
 * serveur MySQL qui sépare alors réellement la requête de ses paramètres,
 * ce qui ferme la porte aux injections SQL.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) Config::get('db.host', 'localhost');
        $name = (string) Config::get('db.name', '');
        $port = (string) Config::get('db.port', '');
        $socket = (string) Config::get('db.socket', '');

        $dsn = 'mysql:';
        $dsn .= $socket !== '' ? "unix_socket={$socket};" : "host={$host};";
        if ($port !== '') {
            $dsn .= "port={$port};";
        }
        $dsn .= "dbname={$name};charset=utf8mb4";

        try {
            self::$connection = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.password', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            // En développement, une trace PDO brute dit « Access denied » sans
            // dire quoi corriger. On la remplace par la marche à suivre, en
            // gardant le message d'origine en cause.
            if (Config::get('debug') === true) {
                throw new RuntimeException(self::explain($e, $name), 0, $e);
            }

            throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        return self::$connection;
    }

    /**
     * Traduit l'échec de connexion en quelque chose d'actionnable.
     *
     * Les trois causes couvrent la quasi-totalité des cas au premier
     * démarrage : identifiants refusés, serveur éteint, base absente.
     */
    private static function explain(PDOException $e, string $name): string
    {
        $code = $e->getCode();
        $detail = $e->getMessage();

        $message = match ((string) $code) {
            '1045' => "MySQL refuse les identifiants de config/config.php.\n"
                . "Vérifiez 'user' et 'password' dans la section 'db'.\n"
                . "Pour savoir si votre compte root a un mot de passe :\n"
                . "  mysql -u root -e \"SELECT 1\"        (sans mot de passe)\n"
                . "  mysql -u root -p -e \"SELECT 1\"     (avec mot de passe)",
            '1049' => "La base « {$name} » n'existe pas encore.\n"
                . "  mysql -u root -p -e \"CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\"\n"
                . '  php database/install.php votre@adresse.fr "un mot de passe long"',
            '2002' => "Le serveur MySQL ne répond pas : il est probablement arrêté.\n"
                . '  brew services start mysql      (macOS)',
            default => 'Connexion à la base impossible.',
        };

        return $message . "\n\nMessage de MySQL : " . $detail;
    }

    /**
     * Raccourci pour une requête préparée.
     *
     * @param array<string|int, mixed> $params
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /** @param array<string|int, mixed> $params @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = self::run($sql, $params)->fetchAll();

        return $rows;
    }

    /** @param array<string|int, mixed> $params @return array<string, mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }
}
