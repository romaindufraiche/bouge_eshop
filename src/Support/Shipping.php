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

        $freeAbove = Config::shop('shipping.free_above_cents');

        if ($freeAbove !== null && $subtotalCents >= (int) $freeAbove) {
            return 0;
        }

        return (int) Config::shop('shipping.flat_rate_cents', 0);
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
}
