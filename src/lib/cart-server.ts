import { PRODUCT_STATUS } from '@/lib/constants';
import { prisma } from '@/lib/prisma';
import { getEffectivePrice, getVariantPrice, formatVariantLabel } from '@/lib/pricing';
import type { CartLine } from '@/lib/cart';
import { computeShippingCents } from '@/lib/shop-config';

/**
 * Résolution du panier côté serveur.
 *
 * C'est LE point qui fait autorité sur les prix : le navigateur n'envoie que
 * des identifiants et des quantités, jamais de montant. Un panier modifié dans
 * la console du navigateur ne permet donc pas d'acheter au prix de son choix.
 */

export type ResolvedCartLine = {
  productId: string;
  variantId: string | null;
  quantity: number;

  // Libellés d'affichage
  name: string;
  slug: string;
  variantLabel: string | null;
  imageUrl: string | null;

  unitPriceCents: number;
  /** Prix barré si le produit est en promotion. */
  compareAtCents: number | null;
  lineTotalCents: number;

  availableStock: number;
};

export type CartIssue = {
  productId: string;
  variantId: string | null;
  /** Libellé connu de l'article concerné, pour un message compréhensible. */
  label: string;
  reason: 'unavailable' | 'out-of-stock' | 'reduced-quantity';
  /** Quantité finalement retenue (0 si l'article a été retiré). */
  keptQuantity: number;
};

export type ResolvedCart = {
  lines: ResolvedCartLine[];
  subtotalCents: number;
  /** Problèmes rencontrés, à afficher au client avant de payer. */
  issues: CartIssue[];
  isEmpty: boolean;
};

export async function resolveCart(lines: CartLine[]): Promise<ResolvedCart> {
  if (lines.length === 0) {
    return { lines: [], subtotalCents: 0, issues: [], isEmpty: true };
  }

  const products = await prisma.product.findMany({
    where: {
      id: { in: [...new Set(lines.map((line) => line.productId))] },
      status: PRODUCT_STATUS.PUBLISHED,
    },
    include: {
      images: { orderBy: { position: 'asc' }, take: 1 },
      variants: true,
    },
  });

  const productsById = new Map(products.map((product) => [product.id, product]));

  const resolved: ResolvedCartLine[] = [];
  const issues: CartIssue[] = [];

  for (const line of lines) {
    const product = productsById.get(line.productId);

    // Produit supprimé ou repassé en brouillon depuis l'ajout au panier.
    if (!product) {
      issues.push({
        productId: line.productId,
        variantId: line.variantId,
        label: 'Un article',
        reason: 'unavailable',
        keptQuantity: 0,
      });
      continue;
    }

    const variant = line.variantId
      ? (product.variants.find((candidate) => candidate.id === line.variantId) ?? null)
      : null;

    // Déclinaison supprimée entre-temps.
    if (line.variantId && !variant) {
      issues.push({
        productId: line.productId,
        variantId: line.variantId,
        label: product.name,
        reason: 'unavailable',
        keptQuantity: 0,
      });
      continue;
    }

    const availableStock = variant ? variant.stock : product.stock;
    const variantLabel = variant ? formatVariantLabel(variant) : null;
    const label = variantLabel ? `${product.name} (${variantLabel})` : product.name;

    if (availableStock <= 0) {
      issues.push({
        productId: line.productId,
        variantId: line.variantId,
        label,
        reason: 'out-of-stock',
        keptQuantity: 0,
      });
      continue;
    }

    // On plafonne à ce qui reste réellement en stock.
    const quantity = Math.min(line.quantity, availableStock);
    if (quantity < line.quantity) {
      issues.push({
        productId: line.productId,
        variantId: line.variantId,
        label,
        reason: 'reduced-quantity',
        keptQuantity: quantity,
      });
    }

    const price = variant
      ? getVariantPrice(product, variant)
      : getEffectivePrice(product);

    resolved.push({
      productId: product.id,
      variantId: variant?.id ?? null,
      quantity,
      name: product.name,
      slug: product.slug,
      variantLabel,
      imageUrl: product.images[0]?.url ?? null,
      unitPriceCents: price.cents,
      compareAtCents: price.compareAtCents,
      lineTotalCents: price.cents * quantity,
      availableStock,
    });
  }

  const subtotalCents = resolved.reduce(
    (total, line) => total + line.lineTotalCents,
    0,
  );

  return {
    lines: resolved,
    subtotalCents,
    issues,
    isEmpty: resolved.length === 0,
  };
}

/** Totaux d'une commande, frais de port inclus. */
export function computeTotals(subtotalCents: number, fulfilment: string) {
  const shippingCents = computeShippingCents(subtotalCents, fulfilment);
  return {
    subtotalCents,
    shippingCents,
    totalCents: subtotalCents + shippingCents,
  };
}
