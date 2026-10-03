<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CustomerRepository;
use Bouge\Support\Auth;
use Bouge\Support\Cart;
use Bouge\Support\Config;
use Bouge\Support\CustomerAuth;
use Bouge\Support\Csrf;
use Bouge\Support\Invoice;
use Bouge\Support\Mailer;
use Bouge\Support\PasswordReset;
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
        if ($email === '' || $password === '') {
            return $this->renderLogin('Adresse ou mot de passe incorrect.', $email);
        }

        if (!CustomerAuth::attempt($email, $password)) {
            // Les identifiants d'administration ouvrent l'administration,
            // depuis ce formulaire aussi. La boutique n'affiche qu'un seul
            // « se connecter » ; s'y faire répondre « mot de passe
            // incorrect » alors que le couple est juste n'a aucun sens pour
            // la personne qui tient le magasin.
            //
            // Ce qui s'ouvre reste la session d'administration, jamais un
            // compte client : les deux clés de session ne se croisent pas,
            // et le mot de passe est vérifié par le même `password_verify`
            // que sur /admin/connexion. Rien n'est relâché ici — la
            // temporisation ci-dessus s'applique d'ailleurs aussi, ce que le
            // formulaire d'administration ne fait pas.
            if (Auth::attempt($email, $password)) {
                // La tentative « client » qui vient d'échouer ne doit pas
                // compter : le couple était bon, au mauvais guichet.
                Session::forget('login_failures');

                redirect('/admin');

                return '';
            }

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

        // Le courriel de bienvenue ne conditionne rien : s'il échoue, le
        // compte existe quand même et le client est déjà connecté.
        Mailer::send(
            $email,
            'Bienvenue chez ' . Config::shop('name', 'la boutique'),
            'bienvenue',
            ['client' => ['email' => $email, 'name' => $validator->value('name')]]
        );

        CustomerAuth::login($id);

        Session::flash('shop', $rattachees > 0
            ? "Bienvenue. Vos {$rattachees} commandes précédentes ont été rattachées à votre compte."
            : 'Bienvenue. Votre compte est créé.');

        redirect('/compte');

        return '';
    }

    // --- Mot de passe oublié -------------------------------------------------

    public function showForgot(): string
    {
        return $this->renderForgot();
    }

    /**
     * Ouvre une demande de réinitialisation.
     *
     * La réponse est la même que l'adresse soit connue ou non. Dire « compte
     * inconnu » offrirait à n'importe qui le moyen de savoir qui est client de
     * la boutique, une adresse à la fois.
     */
    public function forgot(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            return $this->renderForgot(['email' => 'Votre session a expiré. Réessayez.'], $_POST);
        }

        $validator = new Validator($_POST);
        $validator
            ->required('email', 'Indiquez votre adresse électronique.')
            ->email('email', 'Cette adresse ne semble pas valide.');

        if (!$validator->passes()) {
            http_response_code(422);

            return $this->renderForgot($validator->errors(), $validator->values());
        }

        PasswordReset::purge();

        $email = mb_strtolower($validator->value('email'));
        $client = (new CustomerRepository())->findByEmail($email);

        if ($client !== null) {
            $jeton = PasswordReset::open((int) $client['id']);

            Mailer::send(
                $email,
                'Réinitialiser votre mot de passe',
                'mot-de-passe',
                [
                    'lien'   => url('/compte/nouveau-mot-de-passe?jeton=' . $jeton),
                    'heures' => PasswordReset::HOURS,
                ]
            );
        }

        return $this->message(
            'Regardez vos courriels',
            "Si un compte existe avec cette adresse, un lien vient d'y être envoyé. "
            . 'Il est valable ' . PasswordReset::HOURS . ' heures et ne fonctionnera qu\'une fois. '
            . "Pensez à vérifier vos indésirables."
        );
    }

    public function showReset(): string
    {
        $jeton = (string) ($_GET['jeton'] ?? '');

        if (PasswordReset::resolve($jeton) === null) {
            return $this->lienMort();
        }

        return $this->renderReset($jeton);
    }

    public function reset(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            return $this->renderReset((string) ($_POST['jeton'] ?? ''), ['password' => 'Votre session a expiré. Réessayez.']);
        }

        $jeton = (string) ($_POST['jeton'] ?? '');
        $client = PasswordReset::resolve($jeton);

        if ($client === null) {
            return $this->lienMort();
        }

        $motDePasse = (string) ($_POST['password'] ?? '');
        $erreurs = [];

        // La même règle qu'à l'inscription : la longueur protège mieux qu'un
        // mélange imposé de majuscules et de chiffres.
        if (mb_strlen($motDePasse) < 12) {
            $erreurs['password'] = 'Douze caractères minimum. Une phrase courte fait très bien l\'affaire.';
        }

        if ($motDePasse !== (string) ($_POST['password_confirm'] ?? '')) {
            $erreurs['password_confirm'] = 'Les deux mots de passe ne correspondent pas.';
        }

        if ($erreurs !== []) {
            http_response_code(422);

            return $this->renderReset($jeton, $erreurs);
        }

        PasswordReset::complete((int) $client['reset_id'], (int) $client['id'], $motDePasse);

        // On connecte directement : demander de ressaisir le mot de passe
        // qu'on vient de choisir n'apporte rien.
        CustomerAuth::login((int) $client['id']);

        Session::flash('shop', 'Votre mot de passe a été changé.');
        redirect('/compte');

        return '';
    }

    private function lienMort(): string
    {
        return $this->message(
            'Ce lien n\'est plus valable',
            "Il a peut-être expiré, ou déjà servi. Demandez-en un nouveau : c'est sans conséquence, "
            . 'votre mot de passe actuel reste en place jusqu\'à ce que vous en choisissiez un autre.'
        );
    }

    /** @param array<string, string> $errors */
    private function renderForgot(array $errors = [], array $values = []): string
    {
        return View::render('compte/mot-de-passe-oublie', [
            'title'     => 'Mot de passe oublié',
            'canonical' => '/compte/mot-de-passe-oublie',
            'noindex'   => true,
            'errors'    => $errors,
            'values'    => $values,
        ]);
    }

    /** @param array<string, string> $errors */
    private function renderReset(string $jeton, array $errors = []): string
    {
        return View::render('compte/nouveau-mot-de-passe', [
            'title'   => 'Nouveau mot de passe',
            'noindex' => true,
            'jeton'   => $jeton,
            'errors'  => $errors,
        ]);
    }

    private function message(string $titre, string $corps): string
    {
        return View::render('boutique/message', [
            'title'   => $titre,
            'noindex' => true,
            'heading' => $titre,
            'body'    => $corps,
        ]);
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

        // Des coordonnées vides alors que les commandes en portent une :
        // on propose la dernière connue plutôt qu'un formulaire blanc. Rien
        // n'est enregistré tant que la personne n'a pas validé — c'est une
        // proposition, pas une décision prise à sa place.
        $suggere = null;

        if (trim((string) ($client['address_line1'] ?? '')) === '') {
            $suggere = $repository->lastShippingAddress((int) $client['id']);
        }

        return View::render('compte/tableau', [
            'title'    => 'Mon compte',
            'noindex'  => true,
            'client'   => $client,
            'suggere'  => $suggere,
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
    /**
     * La facture d'une commande, en PDF.
     *
     * Même garde que la fiche de commande : on ne sert que les commandes du
     * compte connecté, jamais celles d'un autre, et jamais une commande qui
     * n'a pas été payée.
     */
    public function invoice(array $params): string
    {
        CustomerAuth::require();

        // La même lecture que la fiche de commande : c'est elle qui vérifie
        // que la commande appartient bien au compte connecté.
        $depot = new CustomerRepository();
        $client = CustomerAuth::user();
        $commande = $depot->order((int) $client['id'], (string) $params['reference']);

        if ($commande === null || $commande['paid_at'] === null) {
            http_response_code(404);

            return $this->message(
                'Facture introuvable',
                "Cette commande n'existe pas, n'est pas la vôtre, ou n'a pas encore été payée."
            );
        }

        $pdf = Invoice::render($commande);
        // Relu après l'édition : c'est elle qui a pu attribuer le numéro.
        $commande = $depot->order((int) $client['id'], (string) $params['reference']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . Invoice::filename($commande) . '"');
        header('Content-Length: ' . strlen($pdf));

        return $pdf;
    }

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
