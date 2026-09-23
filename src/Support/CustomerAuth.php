<?php

declare(strict_types=1);

namespace Bouge\Support;

use Bouge\Repository\CustomerRepository;

/**
 * Session client.
 *
 * Volontairement distincte de celle de l'administration (Auth) : un client
 * connecté ne doit à aucun moment approcher de /admin, et les deux clés de
 * session ne se croisent jamais.
 *
 * Le compte reste facultatif. Rien dans le tunnel de commande ne l'exige :
 * il sert à retrouver son panier, ses adresses et ses commandes.
 */
final class CustomerAuth
{
    private const KEY = 'customer_id';
    /** Nombre de tentatives avant temporisation, et durée de celle-ci. */
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public static function attempt(string $email, string $password): bool
    {
        if (self::lockedOut()) {
            return false;
        }

        $client = (new CustomerRepository())->findByEmail($email);

        if ($client === null) {
            // On calcule quand même un hachage : sans cela, le temps de
            // réponse révélerait quelles adresses ont un compte.
            password_verify($password, '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
            self::noteFailure();

            return false;
        }

        if (!password_verify($password, $client['password_hash'])) {
            self::noteFailure();

            return false;
        }

        self::login((int) $client['id']);

        return true;
    }

    /** Ouvre la session : après une inscription comme après une connexion. */
    public static function login(int $customerId): void
    {
        // Un identifiant de session obtenu avant l'authentification ne doit
        // pas rester valable après.
        Session::regenerate();
        Session::set(self::KEY, $customerId);
        Session::forget('login_failures');

        $repository = new CustomerRepository();
        $repository->touchLogin($customerId);

        // Le panier de la session en cours l'emporte sur celui du compte, et
        // les deux sont fusionnés : ce qu'on vient de mettre dans son panier
        // ne doit pas disparaître parce qu'on se connecte.
        Cart::mergeInto($repository->loadCart($customerId));
        Cart::persist();
    }

    public static function logout(): void
    {
        // Le panier est enregistré avant de partir : il sera là au retour.
        Cart::persist();
        Session::forget(self::KEY);
        Cart::clear();
        Session::regenerate();
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get(self::KEY);

        return is_int($id) ? $id : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();

        return $id === null ? null : (new CustomerRepository())->find($id);
    }

    /** À appeler au début de chaque page du compte. */
    public static function require(): void
    {
        if (self::check()) {
            return;
        }

        $cible = $_SERVER['REQUEST_URI'] ?? '/compte';
        Session::set('compte_redirect', is_string($cible) ? $cible : '/compte');

        redirect('/compte/connexion');
    }

    public static function requireToken(): void
    {
        if (Csrf::isValid($_POST['_token'] ?? null)) {
            return;
        }

        Session::flash('shop', 'Votre session a expiré. Réessayez.');
        redirect('/compte');
    }

    // --- Temporisation des tentatives --------------------------------------------
    // Sans elle, un mot de passe faible tombe en quelques minutes face à un
    // outil automatisé. Le compteur vit dans la session : c'est imparfait
    // (changer de session le remet à zéro) mais sans table supplémentaire.

    public static function lockedOut(): bool
    {
        $etat = Session::get('login_failures');

        if (!is_array($etat) || ($etat['count'] ?? 0) < self::MAX_ATTEMPTS) {
            return false;
        }

        return (time() - (int) ($etat['at'] ?? 0)) < self::LOCK_SECONDS;
    }

    public static function lockRemaining(): int
    {
        $etat = Session::get('login_failures');
        $reste = self::LOCK_SECONDS - (time() - (int) ($etat['at'] ?? 0));

        return max(0, $reste);
    }

    private static function noteFailure(): void
    {
        $etat = Session::get('login_failures');
        $etat = is_array($etat) ? $etat : ['count' => 0, 'at' => 0];

        // Le compteur repart à zéro si la dernière tentative est ancienne.
        if ((time() - (int) $etat['at']) > self::LOCK_SECONDS) {
            $etat = ['count' => 0, 'at' => 0];
        }

        Session::set('login_failures', [
            'count' => (int) $etat['count'] + 1,
            'at'    => time(),
        ]);
    }
}
