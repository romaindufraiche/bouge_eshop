<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * L'étiquette achetée auprès du transporteur.
 *
 * C'est le seul livrable qui compte pour le vendeur : une page à imprimer, à
 * scotcher sur le colis, puis à déposer. Le numéro de suivi voyage avec.
 */
final class ShippingLabel
{
    public function __construct
    (
        /** Adresse du PDF à imprimer. */
        public readonly string $url,
        /** Référence de l'envoi chez le transporteur. */
        public readonly string $reference,
        public readonly string $carrier,
        public readonly string $trackingNumber,
    ) {
    }
}
