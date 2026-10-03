<?php

declare(strict_types=1);

namespace Bouge\Support;

use Bouge\Repository\AdminUserRepository;

/**
 * Authentification de l'administration.
 *
 * Volontairement minimale : un compte, un mot de passe, une session PHP.
 * Pas de fournisseur externe ni de dépendance supplémentaire pour un besoin
 * qui se résume à « une seule personne accède à /admin ».
 */
final class Auth
{
    private const KEY = 'admin_user_id';
    /** Portée du compteur de tentatives, distincte de celle des clients. */
    private const THROTTLE = 'admin';

    /**
     * Vérifie les identifiants et ouvre la session.
     * Renvoie false si le couple est incorrect.
     */
    public static function attempt(string $email, string $password): bool
    {
        // Après cinq échecs, la porte se ferme cinq minutes. Le formulaire
        // d'administration était le seul du site à accepter un nombre illimité
        // d'essais — celui qui mène au catalogue, aux commandes et aux
        // coordonnées des clients.
        if (LoginThrottle::lockedOut(self::THROTTLE)) {
            return false;
        }

        $user = (new AdminUserRepository())->findByEmail($email);

        if ($user === null) {
            // On calcule quand même un hachage : sans cela, le temps de
            // réponse révélerait quels courriels existent.
            password_verify($password, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
            LoginThrottle::noteFailure(self::THROTTLE);

            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            LoginThrottle::noteFailure(self::THROTTLE);

            return false;
        }

        // Un identifiant de session obtenu avant l'authentification ne doit
        // pas rester valable après.
        Session::regenerate();
        Session::set(self::KEY, (int) $user['id']);
        LoginThrottle::clear(self::THROTTLE);

        return true;
    }

    public static function lockedOut(): bool
    {
        return LoginThrottle::lockedOut(self::THROTTLE);
    }

    /** Le message à afficher quand la porte est fermée. */
    public static function lockMessage(): string
    {
        return LoginThrottle::message(self::THROTTLE);
    }

    public static function logout(): void
    {
        Session::forget(self::KEY);
        Session::regenerate();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = Session::get(self::KEY);

        if (!is_int($id)) {
            return null;
        }

        return (new AdminUserRepository())->find($id);
    }

    /**
     * À appeler au début de CHAQUE écran et de CHAQUE action de
     * l'administration. Le contrôle ne peut pas reposer uniquement sur la
     * navigation : une action est une requête HTTP à part entière, qui peut
     * être déclenchée directement.
     */
    public static function require(): void
    {
        if (self::check()) {
            return;
        }

        $target = $_SERVER['REQUEST_URI'] ?? '/admin';
        Session::set('admin_redirect', is_string($target) ? $target : '/admin');

        redirect('/admin/connexion');
    }

    /** Vérifie le jeton CSRF d'une action d'administration. */
    public static function requireToken(): void
    {
        if (Csrf::isValid($_POST['_token'] ?? null)) {
            return;
        }

        Session::flash('admin', 'Votre session a expiré. Réessayez.');
        redirect('/admin');
    }
}
