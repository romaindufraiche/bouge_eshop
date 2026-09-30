<?php
/**
 * Courriel de réinitialisation du mot de passe.
 *
 * @var string               $lien
 * @var int                  $heures  Durée de validité du lien
 * @var array<string, mixed> $shop
 * @var string               $sujet
 */

use Bouge\Support\View;

ob_start();
?>
<h1 style="margin:0 0 16px;font:700 22px/1.25 Helvetica,Arial,sans-serif;color:#232323;">
    Choisir un nouveau mot de passe
</h1>

<p style="margin:0 0 24px;">
    Vous avez demandé à réinitialiser le mot de passe de votre compte. Le lien
    ci-dessous est valable <?= (int) $heures ?>&nbsp;heure<?= $heures > 1 ? 's' : '' ?> et
    ne fonctionnera qu'une fois.
</p>

<p style="margin:0 0 24px;">
    <a href="<?= e($lien) ?>"
       style="display:inline-block;padding:12px 22px;border-radius:999px;background:#a8441b;
              color:#ffffff;text-decoration:none;font:600 14px/1 Helvetica,Arial,sans-serif;">
        Choisir un nouveau mot de passe
    </a>
</p>

<p style="margin:0 0 24px;font:400 13px/1.6 Helvetica,Arial,sans-serif;color:#59443a;
          word-break:break-all;">
    Si le bouton ne fonctionne pas, recopiez cette adresse dans votre navigateur&nbsp;:<br>
    <?= e($lien) ?>
</p>

<p style="margin:0;font:400 13px/1.6 Helvetica,Arial,sans-serif;color:#59443a;">
    Vous n'avez rien demandé&nbsp;? Ignorez ce message&nbsp;: votre mot de passe actuel
    reste valable, et le lien expirera de lui-même.
</p>
<?php
echo View::partial('courriels/_enveloppe', [
    'titre' => $sujet,
    'corps' => (string) ob_get_clean(),
    'shop'  => $shop,
]);
