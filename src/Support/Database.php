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
            // Le message d'origine contient les identifiants : on ne le laisse
            // remonter qu'en développement.
            if (Config::get('debug') === true) {
                throw $e;
            }

            throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        return self::$connection;
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
