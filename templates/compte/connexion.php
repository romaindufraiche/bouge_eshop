<?php
/**
 * Connexion client.
 *
 * @var string|null $erreur
 * @var string      $email
 */

use Bouge\Support\Csrf;
?>
<div class="wrap wrap--narrow">
    <div class="section">
        <h1 class="t-xl">Se connecter</h1>
        <p class="muted" style="margin-top:.75rem">
            Pour retrouver votre panier d'un appareil à l'autre et suivre vos commandes.
        </p>

        <?php if ($erreur !== null): ?>
            <p class="notice notice--accent" role="alert" style="margin-top:1.5rem"><?= e($erreur) ?></p>
        <?php endif; ?>

        <form method="post" action="/compte/connexion" class="stack" style="margin-top:2rem">
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

            <button class="btn btn--accent btn--block btn--lg" type="submit">Se connecter</button>
        </form>

        <div class="divider" style="margin-top:2.5rem;padding-top:2rem">
            <h2 class="t-m">Pas encore de compte ?</h2>
            <p class="muted t-s" style="margin-top:.5rem">
                La création prend une minute. Vos commandes passées avec la même
                adresse y seront rattachées automatiquement.
            </p>
            <p style="margin-top:1.25rem">
                <a class="btn btn--ghost" href="/compte/inscription">Créer un compte</a>
            </p>
        </div>

        <p class="t-s muted" style="margin-top:2rem">
            <?php /* Le compte n'est pas un passage obligé : le dire évite de
                     perdre un client au moment de payer. */ ?>
            Vous pouvez aussi <a href="/panier">commander sans compte</a> :
            rien ne l'exige.
        </p>
    </div>
</div>
