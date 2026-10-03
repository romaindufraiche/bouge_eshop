<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Temporisation des tentatives de connexion.
 *
 * Sans elle, un mot de passe faible tombe en quelques minutes face à un outil
 * automatisé. Le compteur vit dans la session : c'est imparfait — changer de
 * session le remet à zéro — mais cela ne demande aucune table, et cela suffit
 * à ralentir un robot qui, lui, ne garde pas les cookies.
 *
 * Cette logique existait côté client, et pas côté administration : le
 * formulaire le plus sensible du site était le seul à accepter un nombre
 * illimité d'essais. Elle est donc sortie de `CustomerAuth` pour que les deux
 * portes la partagent, chacune avec son propre compteur.
 */
final class LoginThrottle
{
    /** Nombre de tentatives avant temporisation, et durée de celle-ci. */
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public static function lockedOut(string $portee): bool
    {
        $etat = Session::get(self::cle($portee));

        if (!is_array($etat) || ($etat['count'] ?? 0) < self::MAX_ATTEMPTS) {
            return false;
        }

        return (time() - (int) ($etat['at'] ?? 0)) < self::LOCK_SECONDS;
    }

    /** Secondes restantes avant de pouvoir réessayer. */
    public static function remaining(string $portee): int
    {
        $etat = Session::get(self::cle($portee));

        return max(0, self::LOCK_SECONDS - (time() - (int) ($etat['at'] ?? 0)));
    }

    /** Le message tout fait, puisque les deux formulaires disent la même chose. */
    public static function message(string $portee): string
    {
        $minutes = (int) ceil(self::remaining($portee) / 60);

        return "Trop de tentatives. Réessayez dans {$minutes} minute" . ($minutes > 1 ? 's' : '') . '.';
    }

    public static function noteFailure(string $portee): void
    {
        $etat = Session::get(self::cle($portee));
        $etat = is_array($etat) ? $etat : ['count' => 0, 'at' => 0];

        // Le compteur repart à zéro si la dernière tentative est ancienne.
        if ((time() - (int) $etat['at']) > self::LOCK_SECONDS) {
            $etat = ['count' => 0, 'at' => 0];
        }

        Session::set(self::cle($portee), [
            'count' => (int) $etat['count'] + 1,
            'at'    => time(),
        ]);
    }

    public static function clear(string $portee): void
    {
        Session::forget(self::cle($portee));
    }

    private static function cle(string $portee): string
    {
        return 'login_failures_' . $portee;
    }
}
