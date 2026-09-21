import { revalidatePath } from 'next/cache';

/**
 * Régénère les pages publiques touchées par une modification faite en admin.
 *
 * Les pages boutique sont statiques et se rafraîchissent d'elles-mêmes toutes
 * les cinq minutes ; cet appel rend le changement visible immédiatement, ce
 * qui est le comportement attendu quand on vient d'enregistrer un prix.
 */
export function revalidateShop(options?: {
  productSlugs?: (string | null | undefined)[];
  categorySlugs?: (string | null | undefined)[];
}): void {
  revalidatePath('/');
  revalidatePath('/boutique');

  for (const slug of options?.categorySlugs ?? []) {
    if (slug) revalidatePath(`/boutique/${slug}`);
  }

  for (const slug of options?.productSlugs ?? []) {
    if (slug) revalidatePath(`/produit/${slug}`);
  }
}
