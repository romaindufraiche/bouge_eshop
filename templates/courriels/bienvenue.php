<?php
/**
 * Courriel de bienvenue, envoyé à la création d'un compte.
 *
 * @var array<string, mixed> $client
 * @var array<string, mixed> $shop
 * @var string               $sujet
 */

use Bouge\Support\View;

$prenom = trim((string) ($client['name'] ?? ''));
$prenom = $prenom === '' ? '' : ' ' . explode(' ', $prenom)[0];

ob_start();
?>
<h1 style="margin:0 0 16px;font:700 22px/1.25 Helvetica,Arial,sans-serif;color:#232323;">
    Bienvenue<?= e($prenom) ?>.
</h1>

<p style="margin:0 0 16px;">
    Votre compte est ouvert. Il vous évitera de ressaisir votre adresse à chaque
    commande, et vous y retrouverez le suivi de vos colis.
</p>

<p style="margin:0 0 24px;color:#59443a;">
    Votre identifiant est l'adresse <strong style="color:#232323;"><?= e($client['email']) ?></strong>.
</p>

<p style="margin:0 0 24px;">
    <a href="<?= e(url('/compte')) ?>"
       style="display:inline-block;padding:12px 22px;border-radius:999px;background:#a8441b;
              color:#ffffff;text-decoration:none;font:600 14px/1 Helvetica,Arial,sans-serif;">
        Voir mon compte
    </a>
</p>

<p style="margin:0;font:400 13px/1.6 Helvetica,Arial,sans-serif;color:#59443a;">
    Vous n'êtes à l'origine d'aucune inscription&nbsp;? Ignorez ce message&nbsp;: sans
    mot de passe, le compte ne sert à personne.
</p>
<?php
echo View::partial('courriels/_enveloppe', [
    'titre' => $sujet,
    'corps' => (string) ob_get_clean(),
    'shop'  => $shop,
]);
