<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Frais de port.
 *
 * Calculés ici et nulle part ailleurs : jamais transmis par un formulaire,
 * sans quoi un client pourrait se livrer gratuitement.
 */
final class Shipping
{
    public static function cents(int $subtotalCents, string $fulfilment): int
    {
        if ($fulfilment === Status::PICKUP) {
            return (int) Config::shop('shipping.pickup_cents', 0);
        }

        // Le franco de port s'applique aux deux modes livrés : un client qui a
        // dépassé le seuil ne comprendrait pas de payer parce qu'il a choisi
        // le point relais, moins cher pour nous.
        $freeAbove = Config::shop('shipping.free_above_cents');

        if ($freeAbove !== null && $subtotalCents >= (int) $freeAbove) {
            return 0;
        }

        if ($fulfilment === Status::RELAY) {
            return (int) Config::shop('shipping.relay_cents', 0);
        }

        return (int) Config::shop('shipping.flat_rate_cents', 0);
    }

    /**
     * Le point relais n'est proposé que si un tarif est fixé ET qu'un
     * transporteur est branché : sans transporteur, personne ne saurait dire
     * quels points relais existent près du client.
     */
    public static function relayAvailable(): bool
    {
        return Config::shop('shipping.relay_cents') !== null
            && (string) Config::shop('carrier.driver', '') !== '';
    }

    /**
     * Les modes de remise réellement proposables, dans l'ordre d'affichage.
     *
     * @param bool $hasPickupPoints Des points de retrait sont-ils configurés ?
     * @return list<string>
     */
    public static function availableFulfilments(bool $hasPickupPoints): array
    {
        $modes = [Status::DELIVERY];

        if (self::relayAvailable()) {
            $modes[] = Status::RELAY;
        }

        if ($hasPickupPoints) {
            $modes[] = Status::PICKUP;
        }

        return $modes;
    }

    /** Ce qu'il manque au panier pour atteindre la livraison offerte. */
    public static function missingForFree(int $subtotalCents): ?int
    {
        $freeAbove = Config::shop('shipping.free_above_cents');

        if ($freeAbove === null) {
            return null;
        }

        $missing = (int) $freeAbove - $subtotalCents;

        return $missing > 0 ? $missing : null;
    }

    /**
     * Poids du colis, en grammes : la somme des articles plus l'emballage.
     *
     * Le transporteur facture au poids. Un produit dont la fiche ne dit rien
     * compte pour le poids par défaut de la configuration — une estimation
     * haute vaut mieux qu'un colis refusé au dépôt.
     *
     * @param list<array{quantity: int, weight_grams?: int|null}> $lines
     */
    public static function parcelWeightGrams(array $lines): int
    {
        $default = (int) Config::shop('shipping.default_weight_grams', 300);
        $total = (int) Config::shop('shipping.packaging_grams', 0);

        foreach ($lines as $line) {
            $unit = (int) ($line['weight_grams'] ?? 0);
            $total += ($unit > 0 ? $unit : $default) * (int) $line['quantity'];
        }

        return max(1, $total);
    }
}
