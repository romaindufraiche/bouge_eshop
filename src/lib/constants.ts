/**
 * Valeurs contrôlées de l'application.
 *
 * Elles sont stockées en `String` dans la base (les `enum` Prisma ne sont pas
 * disponibles sur SQLite) : ce fichier est donc la référence unique pour les
 * valeurs autorisées et leurs libellés affichés.
 */

// --- Statut de publication d'un produit ------------------------------------

export const PRODUCT_STATUS = {
  DRAFT: 'DRAFT',
  PUBLISHED: 'PUBLISHED',
} as const;

export type ProductStatus = (typeof PRODUCT_STATUS)[keyof typeof PRODUCT_STATUS];

export const PRODUCT_STATUS_LABELS: Record<ProductStatus, string> = {
  DRAFT: 'Brouillon',
  PUBLISHED: 'En ligne',
};

// --- Mode de remise de la commande -----------------------------------------

export const FULFILMENT = {
  DELIVERY: 'DELIVERY',
  PICKUP: 'PICKUP',
} as const;

export type Fulfilment = (typeof FULFILMENT)[keyof typeof FULFILMENT];

export const FULFILMENT_LABELS: Record<Fulfilment, string> = {
  DELIVERY: 'Livraison à domicile',
  PICKUP: 'Retrait sur place',
};

// --- Statut d'une commande --------------------------------------------------

export const ORDER_STATUS = {
  /** Commande créée, le client n'a pas encore payé. */
  PENDING: 'PENDING',
  /** Paiement confirmé par Stripe. */
  PAID: 'PAID',
  /** Colis en cours de préparation. */
  PREPARING: 'PREPARING',
  /** Expédiée (livraison) — numéro de suivi transmis au client. */
  SHIPPED: 'SHIPPED',
  /** Retirée par le client (retrait sur place). */
  COLLECTED: 'COLLECTED',
  /** Annulée ou remboursée. */
  CANCELLED: 'CANCELLED',
} as const;

export type OrderStatus = (typeof ORDER_STATUS)[keyof typeof ORDER_STATUS];

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  PENDING: 'En attente de paiement',
  PAID: 'Payée',
  PREPARING: 'En préparation',
  SHIPPED: 'Expédiée',
  COLLECTED: 'Retirée',
  CANCELLED: 'Annulée',
};

/**
 * Statuts proposés dans l'admin, selon le mode de remise choisi par le client.
 * Inutile de proposer « Expédiée » sur une commande à retirer sur place.
 */
export function availableOrderStatuses(fulfilment: string): OrderStatus[] {
  const common: OrderStatus[] = [
    ORDER_STATUS.PENDING,
    ORDER_STATUS.PAID,
    ORDER_STATUS.PREPARING,
  ];
  const final: OrderStatus =
    fulfilment === FULFILMENT.PICKUP ? ORDER_STATUS.COLLECTED : ORDER_STATUS.SHIPPED;

  return [...common, final, ORDER_STATUS.CANCELLED];
}
