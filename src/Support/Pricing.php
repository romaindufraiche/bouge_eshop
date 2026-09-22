<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Calcul des prix et des promotions.
 *
 * Une promotion n'est appliquée que si un prix promo est renseigné, qu'il est
 * inférieur au prix normal, ET que la date du jour tombe dans la fenêtre de
 * validité. Les deux bornes sont facultatives : une date de début seule donne
 * une promo qui démarre puis ne s'arrête pas.
 *
 * @phpstan-type ProductRow array{price_cents: int, sale_price_cents: ?int, sale_starts_at: ?string, sale_ends_at: ?string}
 */
final class Pricing
{
    /** @param array<string, mixed> $product ligne de la table `products` */
    public static function effective(array $product, ?string $today = null): Price
    {
        $today ??= date('Y-m-d');

        $price = (int) $product['price_cents'];
        $sale = $product['sale_price_cents'] === null ? null : (int) $product['sale_price_cents'];
        $starts = $product['sale_starts_at'] ?? null;
        $ends = $product['sale_ends_at'] ?? null;

        $hasSale = $sale !== null && $sale < $price && $price > 0;
        $started = $starts === null || $starts === '' || $starts <= $today;
        $notEnded = $ends === null || $ends === '' || $ends >= $today;

        if (!$hasSale || !$started || !$notEnded) {
            return new Price($price);
        }

        return new Price(
            cents: $sale,
            compareAtCents: $price,
            discountPercent: (int) round((($price - $sale) / $price) * 100),
        );
    }

    /**
     * Prix d'une déclinaison : son prix propre s'il est défini, sinon celui du
     * produit. La promotion du produit s'applique dans les deux cas, en
     * conservant le même pourcentage de remise.
     *
     * @param array<string, mixed>      $product
     * @param array<string, mixed>|null $variant
     */
    public static function forVariant(array $product, ?array $variant, ?string $today = null): Price
    {
        $variantPrice = $variant === null || $variant['price_cents'] === null
            ? null
            : (int) $variant['price_cents'];

        if ($variantPrice === null) {
            return self::effective($product, $today);
        }

        $base = self::effective($product, $today);

        if (!$base->onSale() || $base->compareAtCents === null) {
            return new Price($variantPrice);
        }

        $discounted = (int) round($variantPrice * ($base->cents / $base->compareAtCents));

        return new Price(
            cents: $discounted,
            compareAtCents: $variantPrice,
            discountPercent: $base->discountPercent,
        );
    }

    /** Libellé lisible d'une déclinaison : « M · Noir ». */
    public static function variantLabel(?string $size, ?string $color): ?string
    {
        $parts = array_values(array_filter([
            $size !== null && trim($size) !== '' ? trim($size) : null,
            $color !== null && trim($color) !== '' ? trim($color) : null,
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
