<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * Ce que la boutique attend d'un transporteur.
 *
 * Deux services, et rien de plus : dire quels points relais existent près
 * d'une adresse, et vendre une étiquette. Tout le reste — le tunnel de
 * commande, le calcul du port, l'administration — est écrit contre cette
 * interface et ignore qui est branché derrière.
 *
 * Changer de prestataire, c'est donc écrire une classe et modifier une ligne
 * de `config/shop.php`, pas retoucher le tunnel de commande.
 */
interface Carrier
{
    /** Nom affichable du prestataire, pour l'administration. */
    public function name(): string;

    /**
     * Les points relais proposés autour d'une adresse, du plus proche au plus
     * loin. Un tableau vide n'est pas une erreur : il peut n'y en avoir aucun.
     *
     * @throws CarrierException si le transporteur ne répond pas.
     * @return list<RelayPoint>
     */
    public function relayPointsNear(string $postalCode, string $city, int $limit = 8): array;

    /**
     * Achète une étiquette et renvoie de quoi l'imprimer.
     *
     * @param array<string, mixed> $order      La commande, telle qu'en base.
     * @param int                  $weightGrams Poids du colis.
     * @throws CarrierException si l'achat échoue.
     */
    public function buyLabel(array $order, int $weightGrams): ShippingLabel;
}
