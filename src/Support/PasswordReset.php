<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Réinitialisation du mot de passe d'un compte client.
 *
 * Trois précautions, qui sont l'essentiel du sujet :
 *
 *   - **Le jeton n'est pas stocké.** Seule son empreinte l'est. Une copie de
 *     la base ne permet donc pas de prendre la main sur un compte.
 *   - **Un lien ne sert qu'une fois**, et il expire. Retrouvé dans une boîte
 *     mail six mois plus tard, il ne vaut rien.
 *   - **La demande ne dit jamais si l'adresse est connue.** Répondre « compte
 *     inconnu » offrirait à n'importe qui la liste des clients de la boutique.
 */
final class PasswordReset
{
    /** Au-delà, le lien est mort. Assez pour relever ses courriels, pas plus. */
    public const HOURS = 2;

    /**
     * Ouvre une demande et renvoie le jeton en clair, à mettre dans le lien.
     *
     * Les demandes précédentes du même compte sont invalidées : sans cela, un
     * client qui clique trois fois sur « mot de passe oublié » laisserait trois
     * portes ouvertes derrière lui.
     */
    public static function open(int $customerId): string
    {
        Database::run(
            'UPDATE password_resets SET used_at = NOW()
              WHERE customer_id = ? AND used_at IS NULL',
            [$customerId]
        );

        $jeton = bin2hex(random_bytes(32));

        Database::run(
            'INSERT INTO password_resets (customer_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))',
            [$customerId, hash('sha256', $jeton), self::HOURS]
        );

        return $jeton;
    }

    /**
     * Le compte visé par ce jeton, s'il est encore valable.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $jeton): ?array
    {
        if ($jeton === '' || !ctype_xdigit($jeton)) {
            return null;
        }

        return Database::first(
            'SELECT r.id AS reset_id, c.*
               FROM password_resets r
               JOIN customers c ON c.id = r.customer_id
              WHERE r.token_hash = ?
                AND r.used_at IS NULL
                AND r.expires_at > NOW()',
            [hash('sha256', $jeton)]
        );
    }

    /**
     * Change le mot de passe et referme la demande, d'un seul geste.
     *
     * Les autres demandes en cours pour ce compte tombent aussi : le mot de
     * passe ayant changé, elles n'ont plus lieu d'être.
     */
    public static function complete(int $resetId, int $customerId, string $motDePasse): void
    {
        Database::run(
            'UPDATE customers SET password_hash = ? WHERE id = ?',
            [password_hash($motDePasse, PASSWORD_DEFAULT), $customerId]
        );

        Database::run(
            'UPDATE password_resets SET used_at = NOW()
              WHERE customer_id = ? AND used_at IS NULL',
            [$customerId]
        );
    }

    /**
     * Efface les demandes expirées depuis longtemps.
     *
     * Appelée de temps en temps depuis la page de demande : sans tâche
     * planifiée sur un hébergement mutualisé, c'est le moment le plus naturel
     * pour faire le ménage.
     */
    public static function purge(): void
    {
        // Une chance sur vingt : la table reste petite sans qu'on paie une
        // suppression à chaque visite.
        if (random_int(1, 20) !== 1) {
            return;
        }

        Database::run('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    }
}
