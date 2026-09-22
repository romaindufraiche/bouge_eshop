<?php
/**
 * Page de connexion à l'administration.
 *
 * @var array<string, mixed> $shop
 * @var string|null          $error
 * @var string               $email
 */

use Bouge\Support\Csrf;
?>
<div class="admin-login">
    <div class="admin-login__card">
        <img class="admin-login__logo"
             src="<?= e(asset('/assets/brand/wordmark-anthracite.png')) ?>"
             alt="<?= e($shop['name']) ?>" width="720" height="346">

        <h1 class="t-l">Administration</h1>
        <p class="muted t-s">Connectez-vous pour gérer la boutique.</p>

        <?php if ($error !== null): ?>
            <p class="notice notice--accent" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/admin/connexion" class="stack">
            <?= Csrf::field() ?>

            <div class="field">
                <label for="email">Adresse électronique</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button class="btn btn--block" type="submit">Se connecter</button>
        </form>

        <p class="t-xs muted" style="margin-top:1.5rem">
            <a href="/">Retour à la boutique</a>
        </p>
    </div>
</div>
