<?php

declare(strict_types=1);

namespace Bouge\Shipping;

use RuntimeException;

/**
 * Le transporteur n'a pas répondu, ou a refusé.
 *
 * Distinguée des autres erreurs pour que le tunnel de commande puisse
 * continuer sans point relais au lieu de tomber : un transporteur en panne ne
 * doit pas empêcher une vente en livraison à domicile.
 */
final class CarrierException extends RuntimeException
{
}
