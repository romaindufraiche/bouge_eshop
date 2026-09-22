/**
 * Produits vendus par un tiers.
 *
 * Certains articles de la marque ne sont pas vendus sur cette boutique mais
 * par un revendeur. Leur fiche reste sur le site — pour le référencement et
 * la cohérence du catalogue — mais l'achat se fait ailleurs.
 */

type ExternalFields = {
  externalUrl: string | null;
  externalLabel: string | null;
};

/** Vrai si l'achat se fait chez un tiers, donc hors panier. */
export function isSoldExternally(product: ExternalFields): boolean {
  return Boolean(product.externalUrl && product.externalUrl.trim() !== '');
}

/**
 * Nom du revendeur affiché sur le bouton.
 * À défaut de libellé saisi, on retombe sur le nom de domaine du lien, qui
 * reste parlant pour le client ("fnac.com").
 */
export function sellerName(product: ExternalFields): string {
  if (product.externalLabel?.trim()) return product.externalLabel.trim();
  if (!product.externalUrl) return 'le revendeur';

  try {
    return new URL(product.externalUrl).hostname.replace(/^www\./, '');
  } catch {
    return 'le revendeur';
  }
}
