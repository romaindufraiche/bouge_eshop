<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Prix effectif d'un produit à un instant donné.
 */
final class Price
{
    public function __construct(
        /** Montant réellement facturé, en centimes. */
        public readonly int $cents,
        /** Prix normal à afficher barré. Null hors promotion. */
        public readonly ?int $compareAtCents = null,
        /** Remise en pourcentage entier (20 pour -20 %). Null hors promotion. */
        public readonly ?int $discountPercent = null,
    ) {
    }

    public function onSale(): bool
    {
        return $this->compareAtCents !== null;
    }
}
