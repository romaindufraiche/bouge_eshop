<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * Aucun transporteur branché.
 *
 * L'état par défaut, et un état parfaitement valable : la boutique vend en
 * livraison à domicile au forfait et en retrait sur place, le vendeur achète
 * ses étiquettes comme il le faisait avant. Le point relais n'est simplement
 * pas proposé — mieux vaut ne pas l'offrir que de l'offrir sans pouvoir tenir.
 */
final class NoCarrier implements Carrier
{
    public function name(): string
    {
        return 'Aucun transporteur configuré';
    }

    public function relayPointsNear(string $postalCode, string $city, int $limit = 8): array
    {
        return [];
    }

    public function buyLabel(array $order, int $weightGrams): ShippingLabel
    {
        throw new CarrierException(
            "Aucun transporteur n'est configuré : renseignez « carrier.driver » "
            . 'dans config/shop.php pour acheter des étiquettes depuis le site.'
        );
    }
}
