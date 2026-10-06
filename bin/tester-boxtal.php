<?php

declare(strict_types=1);

/**
 * Éprouve les identifiants Boxtal, sans rien acheter.
 *
 * Trois questions auxquelles il répond, dans l'ordre où elles se posent :
 * les identifiants sont-ils acceptés, le compte voit-il des transporteurs,
 * et ces transporteurs proposent-ils des points relais ?
 *
 *     php bin/tester-boxtal.php            # avec le code postal du siège
 *     php bin/tester-boxtal.php 75011      # ailleurs
 *
 * Il n'appelle que la cotation : aucune commande n'est créée, aucune
 * étiquette achetée, rien n'est facturé — même avec des identifiants de
 * production.
 */

require dirname(__DIR__) . '/src/autoload.php';

use Bouge\Shipping\BoxtalCarrier;
use Bouge\Shipping\CarrierException;
use Bouge\Support\Config;

$codePostal = $argv[1] ?? (string) Config::shop('carrier.from.postal_code', '92400');
$ville = $argv[2] ?? (string) Config::shop('carrier.from.city', 'Courbevoie');

$utilisateur = (string) Config::get('boxtal.user', '');
$motDePasse = (string) Config::get('boxtal.password', '');
$test = (bool) Config::get('boxtal.test', true);

echo "Identifiants lus dans config/config.php\n";
echo '  utilisateur : ' . ($utilisateur !== '' ? $utilisateur : '(vide)') . "\n";
echo '  mot de passe : ' . ($motDePasse !== '' ? str_repeat('•', min(12, strlen($motDePasse))) : '(vide)') . "\n";
echo '  serveur      : ' . ($test ? 'test.envoimoinscher.com (test)' : 'www.envoimoinscher.com (PRODUCTION)') . "\n\n";

if ($utilisateur === '' || $motDePasse === '') {
    fwrite(STDERR, "Renseignez le bloc « boxtal » de config/config.php avant de lancer ce test.\n");
    fwrite(STDERR, "L'API v1 attend l'identifiant et le mot de passe du compte Boxtal,\n");
    fwrite(STDERR, "pas l'identifiant d'une application de l'espace développeur (celui-ci est en v3).\n");
    exit(1);
}

$transporteur = new BoxtalCarrier(
    $utilisateur,
    $motDePasse,
    $test,
    (array) Config::shop('carrier.from', [])
);

echo "Recherche de points relais autour de {$codePostal} {$ville}…\n\n";

try {
    $points = $transporteur->relayPointsNear($codePostal, $ville, 5);
} catch (CarrierException $e) {
    fwrite(STDERR, "✗ Échec : " . $e->getMessage() . "\n\n");
    fwrite(STDERR, "Les causes les plus fréquentes, dans l'ordre :\n");
    fwrite(STDERR, "  - identifiants d'un environnement utilisés sur l'autre (test et production sont distincts) ;\n");
    fwrite(STDERR, "  - accès à l'API v1 pas encore activé sur le compte ;\n");
    fwrite(STDERR, "  - identifiants d'une application v3 au lieu du couple identifiant/mot de passe du compte ;\n");
    fwrite(STDERR, "  - connexions sortantes bloquées par l'hébergement.\n");
    exit(1);
}

if ($points === []) {
    echo "⚠ Les identifiants passent, mais aucun point relais n'est proposé.\n";
    echo "  Le compte est reconnu ; il n'a sans doute aucun transporteur à point\n";
    echo "  relais activé, ou aucun ne dessert ce code postal.\n";
    exit(0);
}

printf("✓ %d point(s) relais trouvé(s) — les identifiants fonctionnent.\n\n", count($points));

foreach ($points as $point) {
    printf(
        "  %-28s %s, %s %s%s\n",
        mb_substr($point->name, 0, 28),
        $point->address,
        $point->postalCode,
        $point->city,
        $point->distanceMeters === null ? '' : sprintf('  (%d m)', $point->distanceMeters)
    );
    printf("  %-28s %s · code %s\n\n", '', $point->operator, $point->code);
}

if (!$test) {
    echo "Rappel : vous êtes sur le serveur de PRODUCTION. Cette requête n'a rien\n";
    echo "coûté — seule la création d'une étiquette est facturée.\n";
}
