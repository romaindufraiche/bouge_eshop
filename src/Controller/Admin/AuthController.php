<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Support\Auth;
use Bouge\Support\Csrf;
use Bouge\Support\Session;
use Bouge\Support\View;

final class AuthController
{
    public function showLogin(): string
    {
        if (Auth::check()) {
            redirect('/admin');
        }

        return View::render('admin/connexion', [
            'title'   => 'Connexion',
            'error'   => null,
            'email'   => '',
        ], 'layout/blank');
    }

    public function login(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            return $this->fail('Votre session a expiré. Réessayez.', (string) ($_POST['email'] ?? ''));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->fail('Renseignez votre adresse et votre mot de passe.', $email);
        }

        // Le message ne distingue jamais « courriel inconnu » de « mot de
        // passe incorrect » : le préciser renseignerait sur l'existence du
        // compte.
        if (!Auth::attempt($email, $password)) {
            return $this->fail('Adresse ou mot de passe incorrect.', $email);
        }

        $target = Session::get('admin_redirect', '/admin');
        Session::forget('admin_redirect');

        // On ne redirige que vers une page interne : un paramètre forgé ne
        // peut pas servir à renvoyer vers un site extérieur après connexion.
        redirect(is_string($target) && str_starts_with($target, '/admin') ? $target : '/admin');

        return '';
    }

    public function logout(): string
    {
        Auth::requireToken();
        Auth::logout();

        redirect('/admin/connexion');

        return '';
    }

    private function fail(string $message, string $email): string
    {
        http_response_code(422);

        return View::render('admin/connexion', [
            'title' => 'Connexion',
            'error' => $message,
            // L'adresse saisie est réaffichée : sans cela il faut la retaper
            // à chaque tentative.
            'email' => $email,
        ], 'layout/blank');
    }
}
