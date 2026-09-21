'use client';

import { readCart, writeCart, type CartLine } from '@/lib/cart';

/**
 * Le panier vu comme une source de données extérieure à React.
 *
 * Il vit dans le localStorage, partagé entre tous les onglets ouverts : ce
 * n'est pas un état React, et le traiter comme tel obligerait à le recopier
 * dans un effet à chaque montage. On l'expose donc via l'API prévue pour cela,
 * `useSyncExternalStore`, qui gère aussi proprement le rendu serveur.
 */

const listeners = new Set<() => void>();

/** Référence stable renvoyée au rendu serveur : le panier y est inconnu. */
const EMPTY: CartLine[] = [];

/**
 * Dernière valeur lue. `useSyncExternalStore` compare les références : relire
 * et reparser le localStorage à chaque rendu renverrait un nouveau tableau et
 * provoquerait une boucle de rendus.
 */
let cache: CartLine[] | null = null;

let isListeningToOtherTabs = false;

function notify(): void {
  for (const listener of listeners) listener();
}

function handleStorageEvent(): void {
  cache = null;
  notify();
}

export const cartStore = {
  subscribe(listener: () => void): () => void {
    listeners.add(listener);

    // Un seul écouteur d'onglet pour tous les abonnés.
    if (!isListeningToOtherTabs) {
      window.addEventListener('storage', handleStorageEvent);
      isListeningToOtherTabs = true;
    }

    return () => {
      listeners.delete(listener);

      if (listeners.size === 0 && isListeningToOtherTabs) {
        window.removeEventListener('storage', handleStorageEvent);
        isListeningToOtherTabs = false;
      }
    };
  },

  getSnapshot(): CartLine[] {
    cache ??= readCart();
    return cache;
  },

  getServerSnapshot(): CartLine[] {
    return EMPTY;
  },

  /** Remplace le panier et prévient tous les abonnés. */
  set(next: CartLine[]): void {
    cache = next;
    writeCart(next);
    notify();
  },

  /** Lecture directe, hors rendu, pour partir de l'état le plus à jour. */
  read(): CartLine[] {
    return this.getSnapshot();
  },
};
