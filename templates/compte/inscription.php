<?php
/**
 * Création de compte.
 *
 * @var array<string, string> $erreurs
 * @var array<string, mixed>  $valeurs
 */

use Bouge\Support\Csrf;

$v = static fn (string $cle): string => (string) ($valeurs[$cle] ?? '');
?>
<div class="wrap wrap--narrow">
    <div class="section">
        <h1 class="t-xl">Créer un compte</h1>
        <p class="muted" style="margin-top:.75rem">
            Trois champs. Ensuite, votre panier vous suit d'un appareil à l'autre
            et vos commandes se retrouvent en deux clics.
        </p>

        <form method="post" action="/compte/inscription" class="stack" style="margin-top:2rem">
            <?= Csrf::field() ?>

            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="<?= e($v('name')) ?>"
                       autocomplete="name" maxlength="120" required autofocus>
                <?php if (isset($erreurs['name'])): ?>
                    <p class="field-error"><?= e($erreurs['name']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">Adresse électronique</label>
                <input type="email" id="email" name="email" value="<?= e($v('email')) ?>"
                       autocomplete="email" required>
                <?php if (isset($erreurs['email'])): ?>
                    <p class="field-error"><?= e($erreurs['email']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="phone">Téléphone (facultatif)</label>
                <input type="tel" id="phone" name="phone" value="<?= e($v('phone')) ?>"
                       autocomplete="tel" maxlength="30">
                <p class="field-help">Utilisé uniquement si le transporteur doit vous joindre.</p>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" minlength="12" required>
                <p class="field-help">
                    Douze caractères minimum. Une phrase courte protège mieux qu'un
                    mot compliqué, et se retient.
                </p>
                <?php if (isset($erreurs['password'])): ?>
                    <p class="field-error"><?= e($erreurs['password']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" required>
                <?php if (isset($erreurs['password_confirm'])): ?>
                    <p class="field-error"><?= e($erreurs['password_confirm']) ?></p>
                <?php endif; ?>
            </div>

            <button class="btn btn--accent btn--block btn--lg" type="submit">Créer mon compte</button>
        </form>

        <p class="t-s muted" style="margin-top:2rem">
            Déjà un compte ? <a href="/compte/connexion">Se connecter</a>.
        </p>
    </div>
</div>
