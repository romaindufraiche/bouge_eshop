<?php

declare(strict_types=1);

namespace Bouge\Shipping;

use Bouge\Support\Config;

/**
 * Fabrique le transporteur décrit par la configuration.
 *
 * Un seul endroit sait quels pilotes existent. Ajouter un prestataire, c'est
 * écrire une classe qui implémente Carrier et ajouter un `case` ici.
 */
final class Carriers
{
    private static ?Carrier $instance = null;

    public static function get(): Carrier
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = (string) Config::shop('carrier.driver', '');

        self::$instance = match ($driver) {
            // Points relais inventés, pour regarder le tunnel avant d'avoir
            // ouvert un compte. Jamais en production.
            'demonstration' => new DemoCarrier(),
            // Un pilote inconnu vaut mieux qu'une page blanche : la boutique
            // continue de vendre, sans point relais.
            default => new NoCarrier(),
        };

        return self::$instance;
    }

    public static function configured(): bool
    {
        return !self::get() instanceof NoCarrier;
    }

    /** Utilisé par les tests pour injecter un transporteur factice. */
    public static function set(?Carrier $carrier): void
    {
        self::$instance = $carrier;
    }
}
