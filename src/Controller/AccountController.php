<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CustomerRepository;
use Bouge\Support\Cart;
use Bouge\Support\CustomerAuth;
use Bouge\Support\Csrf;
use Bouge\Support\Session;
use Bouge\Support\Validator;
use Bouge\Support\View;

/**
 * Espace client : connexion, inscription, commandes et suivi.
 *
 * Le compte est un service, pas un péage : on peut commander sans, et rien
 * ici n'est nécessaire pour acheter. Il sert à retrouver son panier d'un
 * appareil à l'autre, à ne pas retaper son adresse, et à suivre ses colis.
 */
final class AccountController
{
    public function showLogin(): string
    {
        if (CustomerAuth::check()) {
            redirect('/compte');
        }

        return $this->renderLogin();
    }

    public function login(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            return $this->renderLogin('Votre session a expiré. Réessayez.');
        }

        if (CustomerAuth::lockedOut()) {
            $minutes = (int) ceil(CustomerAuth::lockRemaining() / 60);

            return $this->renderLogin(
                "Trop de tentatives. Réessayez dans {$minutes} minute" . ($minutes > 1 ? 's' : '') . '.'
            );
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // Le message ne distingue jamais « adresse inconnue » de « mot de
        // passe incorrect » : le préciser renseignerait sur l'existence du
        // compte.
        if ($email === '' || $password === '' || !CustomerAuth::attempt($email, $password)) {
            return $this->renderLogin('Adresse ou mot de passe incorrect.', $email);
        }

        $cible = Session::get('compte_redirect', '/compte');
        Session::forget('compte_redirect');

        // Redirection interne seulement : un paramètre forgé ne doit pas
        // servir à renvoyer ailleurs après connexion.
        redirect(is_string($cible) && str_starts_with($cible, '/') && !str_starts_with($cible, '//')
            ? $cible
            : '/compte');

        return '';
    }

    public function showRegister(): string
    {
        if (CustomerAuth::check()) {
            redirect('/compte');
        }

        return $this->renderRegister([], []);
    }

    public function register(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            return $this->renderRegister(['email' => 'Votre session a expiré. Réessayez.'], $_POST);
        }

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Indiquez votre nom.')
            ->maxLength('name', 120, 'Nom trop long (120 caractères maximum).')
            ->required('email', 'Indiquez votre adresse électronique.')
            ->email('email', "Cette adresse ne semble pas valide.")
            ->required('password', 'Choisissez un mot de passe.');

        $password = (string) ($_POST['password'] ?? '');

        // Douze caractères plutôt qu'un mélange imposé de majuscules et de
        // chiffres : la longueur protège mieux, et une phrase se retient.
        if ($password !== '' && mb_strlen($password) < 12) {
            $validator->fail('password', 'Douze caractères minimum. Une phrase courte fait très bien l’affaire.');
        }

        if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $validator->fail('password_confirm', 'Les deux mots de passe ne correspondent pas.');
        }

        $repository = new CustomerRepository();
        $email = mb_strtolower($validator->value('email'));

        if ($email !== '' && $repository->findByEmail($email) !== null) {
            $validator->fail('email', 'Un compte existe déjà avec cette adresse. Connectez-vous.');
        }

        if (!$validator->passes()) {
            http_response_code(422);

            return $this->renderRegister($validator->errors(), $validator->values());
        }

        $id = $repository->create(
            $email,
            $password,
            $validator->value('name'),
            $validator->value('phone') ?: null
        );

        // Les commandes déjà passées avec cette adresse rejoignent le compte :
        // sans cela, l'historique commencerait vide alors que le client a
        // déjà acheté.
        $rattachees = $repository->claimOrders($id, $email);

        CustomerAuth::login($id);

        Session::flash('shop', $rattachees > 0
            ? "Bienvenue. Vos {$rattachees} commandes précédentes ont été rattachées à votre compte."
            : 'Bienvenue. Votre compte est créé.');

        redirect('/compte');

        return '';
    }

    public function logout(): string
    {
        CustomerAuth::requireToken();
        CustomerAuth::logout();

        Session::flash('shop', 'Vous êtes déconnecté.');
        redirect('/');

        return '';
    }

    /** Tableau de bord : commandes et coordonnées. */
    public function index(): string
    {
        CustomerAuth::require();

        $client = CustomerAuth::user();
        $repository = new CustomerRepository();

        return View::render('compte/tableau', [
            'title'    => 'Mon compte',
            'noindex'  => true,
            'client'   => $client,
            'commandes' => $repository->orders((int) $client['id']),
            'panier'   => Cart::count(),
        ]);
    }

    /** Détail d'une commande du client. */
    public function order(array $params): string
    {
        CustomerAuth::require();

        $client = CustomerAuth::user();
        $commande = (new CustomerRepository())->order((int) $client['id'], $params['reference']);

        if ($commande === null) {
            http_response_code(404);

            return View::render('boutique/404', [
                'title'   => 'Commande introuvable',
                'noindex' => true,
            ]);
        }

        return View::render('compte/commande', [
            'title'    => 'Commande ' . $commande['reference'],
            'noindex'  => true,
            'commande' => $commande,
        ]);
    }

    /** Coordonnées : nom, téléphone, adresse de livraison par défaut. */
    public function updateProfile(): string
    {
        CustomerAuth::require();
        CustomerAuth::requireToken();

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Indiquez votre nom.')
            ->maxLength('name', 120, 'Nom trop long (120 caractères maximum).')
            ->maxLength('address_line1', 200, 'Adresse trop longue.')
            ->maxLength('city', 120, 'Nom de ville trop long.')
            ->pattern('postal_code', '/^\d{5}$/', 'Le code postal doit comporter cinq chiffres.');

        if (!$validator->passes()) {
            // Le premier message suffit : le formulaire tient en six champs.
            $messages = $validator->errors();
            Session::flash('shop', (string) (array_values($messages)[0] ?? 'Certains champs sont à corriger.'));
            redirect('/compte');
        }

        (new CustomerRepository())->updateProfile((int) CustomerAuth::id(), [
            'name'          => $validator->value('name'),
            'phone'         => $validator->value('phone') ?: null,
            'address_line1' => $validator->value('address_line1') ?: null,
            'address_line2' => $validator->value('address_line2') ?: null,
            'postal_code'   => $validator->value('postal_code') ?: null,
            'city'          => $validator->value('city') ?: null,
        ]);

        Session::flash('shop', 'Vos coordonnées sont enregistrées.');
        redirect('/compte');

        return '';
    }

    // --- Rendu ------------------------------------------------------------------

    private function renderLogin(?string $erreur = null, string $email = ''): string
    {
        if ($erreur !== null) {
            http_response_code(422);
        }

        return View::render('compte/connexion', [
            'title'   => 'Se connecter',
            'noindex' => true,
            'erreur'  => $erreur,
            'email'   => $email,
        ]);
    }

    /**
     * @param array<string, string> $erreurs
     * @param array<string, mixed>  $valeurs
     */
    private function renderRegister(array $erreurs, array $valeurs): string
    {
        return View::render('compte/inscription', [
            'title'   => 'Créer un compte',
            'noindex' => true,
            'erreurs' => $erreurs,
            'valeurs' => $valeurs,
        ]);
    }
}
