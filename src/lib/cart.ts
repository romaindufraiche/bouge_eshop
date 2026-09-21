/**
 * Panier côté navigateur.
 *
 * Le panier ne stocke QUE des identifiants et des quantités. Les libellés, les
 * prix et les stocks sont toujours relus depuis la base au moment de l'affichage
 * (API /api/panier) et de la commande : un prix modifié dans l'admin est donc
 * immédiatement répercuté, et un panier bricolé côté client ne permet pas
 * d'acheter à un prix choisi par l'acheteur.
 */
import { CART } from '@/lib/shop-config';

export type CartLine = {
  productId: string;
  /** Null pour un produit sans déclinaison. */
  variantId: string | null;
  quantity: number;
};

/** Clé d'identité d'une ligne : un même produit en deux tailles = deux lignes. */
export function lineKey(line: Pick<CartLine, 'productId' | 'variantId'>): string {
  return `${line.productId}::${line.variantId ?? ''}`;
}

export function readCart(): CartLine[] {
  if (typeof window === 'undefined') return [];

  try {
    const raw = window.localStorage.getItem(CART.cookieName);
    if (!raw) return [];

    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    // On revalide chaque ligne : le localStorage peut contenir n'importe quoi
    // (ancienne version du site, édition manuelle, extension tierce).
    return parsed.flatMap((entry): CartLine[] => {
      if (typeof entry !== 'object' || entry === null) return [];
      const line = entry as Record<string, unknown>;

      if (typeof line.productId !== 'string' || line.productId === '') return [];
      if (typeof line.quantity !== 'number' || !Number.isInteger(line.quantity)) {
        return [];
      }
      if (line.quantity < 1) return [];

      return [
        {
          productId: line.productId,
          variantId: typeof line.variantId === 'string' ? line.variantId : null,
          quantity: Math.min(line.quantity, CART.maxQuantityPerLine),
        },
      ];
    });
  } catch {
    // localStorage inaccessible (navigation privée, cookies bloqués) : on
    // repart d'un panier vide plutôt que de casser la page.
    return [];
  }
}

export function writeCart(lines: CartLine[]): void {
  if (typeof window === 'undefined') return;

  try {
    window.localStorage.setItem(CART.cookieName, JSON.stringify(lines));
  } catch {
    // Quota dépassé ou stockage refusé : le panier reste valable le temps de
    // la session en mémoire, on n'interrompt pas le parcours d'achat.
  }
}

/** Ajoute une ligne, ou incrémente la quantité si elle existe déjà. */
export function addLine(lines: CartLine[], incoming: CartLine): CartLine[] {
  const key = lineKey(incoming);
  const existing = lines.find((line) => lineKey(line) === key);

  if (!existing) {
    return [...lines, { ...incoming, quantity: clampQuantity(incoming.quantity) }];
  }

  return lines.map((line) =>
    lineKey(line) === key
      ? { ...line, quantity: clampQuantity(line.quantity + incoming.quantity) }
      : line,
  );
}

/** Fixe une quantité. À 0 (ou moins), la ligne est retirée. */
export function setLineQuantity(
  lines: CartLine[],
  target: Pick<CartLine, 'productId' | 'variantId'>,
  quantity: number,
): CartLine[] {
  const key = lineKey(target);

  if (quantity <= 0) {
    return lines.filter((line) => lineKey(line) !== key);
  }

  return lines.map((line) =>
    lineKey(line) === key ? { ...line, quantity: clampQuantity(quantity) } : line,
  );
}

export function removeLine(
  lines: CartLine[],
  target: Pick<CartLine, 'productId' | 'variantId'>,
): CartLine[] {
  const key = lineKey(target);
  return lines.filter((line) => lineKey(line) !== key);
}

export function countItems(lines: CartLine[]): number {
  return lines.reduce((total, line) => total + line.quantity, 0);
}

function clampQuantity(quantity: number): number {
  return Math.max(1, Math.min(Math.trunc(quantity), CART.maxQuantityPerLine));
}
