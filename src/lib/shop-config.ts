/**
 * Réglages commerciaux de la boutique.
 *
 * Tout ce qui se règle « au doigt mouillé » et peut changer sans toucher au
 * code métier est regroupé ici : frais de port, seuil de franco, coordonnées.
 * Modifier une valeur dans ce fichier suffit, aucune migration de base requise.
 */

export const SHOP = {
  name: 'BOUGE.',
  /** Baseline officielle de la marque, présente dans le logo. */
  baseline: 'Sport et bien plus.',
  /** Ce que vend la boutique. Utilisé dans les titres de page et le référencement. */
  tagline: 'Matériel de natation',
  email: 'contact@bouge.fr',
  /** Laisser vide pour masquer la ligne dans le pied de page. */
  phone: '',
  instagram: '',
} as const;

// --- Livraison --------------------------------------------------------------

export const SHIPPING = {
  /**
   * Frais de port forfaitaires pour la France métropolitaine, en centimes.
   * (Hypothèse de départ, à ajuster : le cahier des charges ne fixait pas
   * de grille tarifaire.)
   */
  flatRateCents: 490,

  /**
   * Montant de panier à partir duquel la livraison est offerte, en centimes.
   * Mettre `null` pour désactiver le franco de port.
   */
  freeAboveCents: 6000,

  /** Le retrait sur place est toujours gratuit. */
  pickupCents: 0,

  /** Pays livrés. Le tunnel ne propose que la France pour l'instant. */
  countries: [{ code: 'FR', label: 'France' }],
} as const;

/**
 * Frais de port d'une commande, en centimes.
 * @param subtotalCents total des articles, hors frais de port
 * @param fulfilment "DELIVERY" ou "PICKUP"
 */
export function computeShippingCents(
  subtotalCents: number,
  fulfilment: string,
): number {
  if (fulfilment === 'PICKUP') return SHIPPING.pickupCents;

  if (
    SHIPPING.freeAboveCents !== null &&
    subtotalCents >= SHIPPING.freeAboveCents
  ) {
    return 0;
  }

  return SHIPPING.flatRateCents;
}

/** Ce qu'il manque au panier pour atteindre la livraison offerte, en centimes. */
export function centsUntilFreeShipping(subtotalCents: number): number | null {
  if (SHIPPING.freeAboveCents === null) return null;
  const missing = SHIPPING.freeAboveCents - subtotalCents;
  return missing > 0 ? missing : null;
}

// --- Panier -----------------------------------------------------------------

export const CART = {
  /** Nom du cookie qui porte le panier (lisible côté serveur ET client). */
  cookieName: 'bouge_cart',
  /** Durée de vie du panier, en jours. */
  cookieMaxAgeDays: 30,
  /** Garde-fou : quantité maximale par ligne. */
  maxQuantityPerLine: 20,
} as const;
