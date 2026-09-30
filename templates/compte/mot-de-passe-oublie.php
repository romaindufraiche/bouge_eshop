<?php
/**
 * Demande de réinitialisation du mot de passe.
 *
 * @var array<string, string> $errors
 * @var array<string, string> $values
 */

use Bouge\Support\Csrf;

$v = static fn (string $cle): string => (string) ($values[$cle] ?? '');
?>
<div class="wrap wrap--narrow">
    <div class="section">
        <p class="eyebrow"><a class="link-quiet" href="/compte/connexion">← Connexion</a></p>
        <h1 class="t-xl" style="margin-top:.5rem">Mot de passe oublié</h1>

        <p class="muted" style="margin-top:1rem">
            Indiquez l'adresse de votre compte : nous vous enverrons un lien pour en
            choisir un nouveau.
        </p>

        <form method="post" action="/compte/mot-de-passe-oublie" class="stack" style="margin-top:2rem">
            <?= Csrf::field() ?>

            <div class="field">
                <label for="email">Adresse électronique</label>
                <input type="email" id="email" name="email" value="<?= e($v('email')) ?>"
                       autocomplete="email" required autofocus>
                <?php if (isset($errors['email'])): ?>
                    <p class="field-error"><?= e($errors['email']) ?></p>
                <?php endif; ?>
            </div>

            <button class="btn btn--accent btn--lg" type="submit">Envoyer le lien</button>
        </form>
    </div>
</div>
