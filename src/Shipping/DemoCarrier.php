<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * Faux transporteur, pour regarder le tunnel de commande.
 *
 * Il invente des points relais plausibles autour du code postal demandé, de
 * quoi dérouler le parcours d'achat de bout en bout sans avoir ouvert de
 * compte chez un prestataire. Il ne sait pas acheter d'étiquette, et le dit.
 *
 * À NE JAMAIS LAISSER EN PRODUCTION : un client choisirait un point relais
 * qui n'existe pas. Le nom affiché dans l'administration le rappelle.
 */
final class DemoCarrier implements Carrier
{
    /** Des commerces crédibles, du plus banal au plus banal. */
    private const ENSEIGNES = [
        ['Tabac de la Mairie', 'Presse'],
        ['Superette Le Panier', 'Alimentation'],
        ['Pressing des Écoles', 'Pressing'],
        ['Fleurs & Compagnie', 'Fleuriste'],
        ['Cordonnerie du Centre', 'Cordonnerie'],
        ['Boulangerie Saint-Jean', 'Boulangerie'],
        ['Point Presse du Stade', 'Presse'],
        ['Épicerie des Halles', 'Alimentation'],
    ];

    private const RUES = [
        'rue de la République', 'avenue du Général-Leclerc', 'rue des Écoles',
        'place du Marché', 'boulevard Voltaire', 'rue Jean-Jaurès',
        'avenue de la Gare', 'rue du Moulin',
    ];

    public function name(): string
    {
        return 'Démonstration — aucun colis ne partira';
    }

    public function relayPointsNear(string $postalCode, string $city, int $limit = 8): array
    {
        if (!preg_match('/^\d{5}$/', $postalCode)) {
            return [];
        }

        // Une graine tirée du code postal : la même recherche redonne toujours
        // la même liste, sans quoi le point choisi disparaîtrait à la
        // validation du formulaire.
        mt_srand((int) $postalCode);

        $ville = $city !== '' ? $city : 'Votre ville';
        $points = [];

        foreach (array_slice(self::ENSEIGNES, 0, $limit) as $index => [$enseigne, $metier]) {
            $points[] = new RelayPoint(
                code: 'DEMO-' . $postalCode . '-' . ($index + 1),
                operator: 'Démonstration',
                name: $enseigne,
                address: mt_rand(1, 98) . ' ' . self::RUES[$index % count(self::RUES)],
                postalCode: $postalCode,
                city: $ville,
                schedule: ['Du mardi au samedi, 8h – 19h30', $metier],
                distanceMeters: 150 + $index * mt_rand(80, 400),
            );
        }

        // Du plus proche au plus loin : c'est l'ordre que le client attend.
        usort($points, static fn (RelayPoint $a, RelayPoint $b): int
            => $a->distanceMeters <=> $b->distanceMeters);

        return $points;
    }

    public function relayPoint(string $code): ?RelayPoint
    {
        // Le code porte le code postal : de quoi reconstruire la même liste et
        // y retrouver le point, sans rien stocker.
        if (!preg_match('/^DEMO-(\d{5})-\d+$/', $code, $trouve)) {
            return null;
        }

        foreach ($this->relayPointsNear($trouve[1], '') as $point) {
            if ($point->code === $code) {
                return $point;
            }
        }

        return null;
    }

    public function buyLabel(array $order, int $weightGrams): ShippingLabel
    {
        throw new CarrierException(
            "Le transporteur de démonstration ne fabrique pas d'étiquettes. "
            . 'Ouvrez un compte chez un vrai prestataire et changez « carrier.driver » '
            . 'dans config/shop.php.'
        );
    }
}
