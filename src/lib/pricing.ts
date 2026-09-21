import type { Product, ProductVariant } from '@/lib/prisma';

/**
 * Prix effectif d'un produit à un instant donné.
 *
 * Une promotion n'est appliquée que si un prix promo est renseigné ET que la
 * date du jour tombe dans la fenêtre de validité. Les deux bornes sont
 * optionnelles : `saleStartsAt` seul = promo qui démarre puis ne s'arrête pas,
 * `saleEndsAt` seul = promo active immédiatement jusqu'à cette date.
 */
export type EffectivePrice = {
  /** Montant réellement facturé, en centimes. */
  cents: number;
  /** Prix normal à afficher barré, en centimes. Null hors promotion. */
  compareAtCents: number | null;
  /** Vrai si une promotion est active. */
  onSale: boolean;
  /** Remise en pourcentage entier (ex. 20 pour -20 %). Null hors promotion. */
  discountPercent: number | null;
};

type PriceInput = Pick<
  Product,
  'priceCents' | 'salePriceCents' | 'saleStartsAt' | 'saleEndsAt'
>;

export function getEffectivePrice(
  product: PriceInput,
  now: Date = new Date(),
): EffectivePrice {
  const { priceCents, salePriceCents, saleStartsAt, saleEndsAt } = product;

  const hasSalePrice = salePriceCents !== null && salePriceCents < priceCents;
  const started = !saleStartsAt || saleStartsAt <= now;
  const notEnded = !saleEndsAt || saleEndsAt >= now;

  if (!hasSalePrice || !started || !notEnded) {
    return {
      cents: priceCents,
      compareAtCents: null,
      onSale: false,
      discountPercent: null,
    };
  }

  return {
    cents: salePriceCents,
    compareAtCents: priceCents,
    onSale: true,
    discountPercent: Math.round(((priceCents - salePriceCents) / priceCents) * 100),
  };
}

/**
 * Prix d'une variante : son prix propre s'il est défini, sinon celui du
 * produit. La promotion du produit s'applique dans les deux cas, en conservant
 * l'écart relatif.
 */
export function getVariantPrice(
  product: PriceInput,
  variant: Pick<ProductVariant, 'priceCents'> | null,
  now: Date = new Date(),
): EffectivePrice {
  if (!variant || variant.priceCents === null) {
    return getEffectivePrice(product, now);
  }

  const base = getEffectivePrice(product, now);
  if (!base.onSale || base.compareAtCents === null) {
    return {
      cents: variant.priceCents,
      compareAtCents: null,
      onSale: false,
      discountPercent: null,
    };
  }

  // On reporte le même pourcentage de remise sur le prix de la variante.
  const ratio = base.cents / base.compareAtCents;
  const discounted = Math.round(variant.priceCents * ratio);

  return {
    cents: discounted,
    compareAtCents: variant.priceCents,
    onSale: true,
    discountPercent: base.discountPercent,
  };
}

/** Libellé lisible d'une variante : "Taille M · Noir". */
export function formatVariantLabel(
  variant: Pick<ProductVariant, 'size' | 'color'>,
): string {
  const parts: string[] = [];
  if (variant.size) parts.push(variant.size);
  if (variant.color) parts.push(variant.color);
  return parts.join(' · ');
}
