<?php
/**
 * Choix d'un nouveau mot de passe, depuis le lien reçu par courriel.
 *
 * @var string                $jeton
 * @var array<string, string> $errors
 */

use Bouge\Support\Csrf;
?>
<div class="wrap wrap--narrow">
    <div class="section">
        <h1 class="t-xl">Choisir un nouveau mot de passe</h1>

        <form method="post" action="/compte/nouveau-mot-de-passe" class="stack" style="margin-top:2rem">
            <?= Csrf::field() ?>
            <?php /* Le jeton voyage avec le formulaire : la page est atteinte
                     par un lien, et le champ évite de le remettre dans
                     l'adresse au moment de l'envoi. */ ?>
            <input type="hidden" name="jeton" value="<?= e($jeton) ?>">

            <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" minlength="12" required autofocus>
                <p class="field-help">
                    Douze caractères minimum. Une phrase courte protège mieux qu'un
                    mot compliqué, et se retient.
                </p>
                <?php if (isset($errors['password'])): ?>
                    <p class="field-error"><?= e($errors['password']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" minlength="12" required>
                <?php if (isset($errors['password_confirm'])): ?>
                    <p class="field-error"><?= e($errors['password_confirm']) ?></p>
                <?php endif; ?>
            </div>

            <button class="btn btn--accent btn--lg" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
