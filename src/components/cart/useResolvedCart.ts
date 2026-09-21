'use client';

import { useEffect, useState } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import type { ResolvedCart } from '@/lib/cart-server';

type FetchResult = {
  /** Panier auquel ce résultat correspond, sous forme sérialisée. */
  key: string;
  cart: ResolvedCart | null;
  hasFailed: boolean;
};

/**
 * Récupère le détail chiffré du panier auprès du serveur.
 *
 * Le contenu du panier vit dans le navigateur, mais les prix, les promotions
 * et les stocks viennent toujours de la base : ce hook fait le pont entre les
 * deux et relance l'appel à chaque modification du panier.
 */
export function useResolvedCart() {
  const { lines, isReady } = useCart();
  const [result, setResult] = useState<FetchResult | null>(null);

  const key = JSON.stringify(lines);

  useEffect(() => {
    if (!isReady) return;

    const controller = new AbortController();

    // Toutes les mises à jour d'état ont lieu APRÈS l'appel réseau, jamais de
    // façon synchrone dans le corps de l'effet.
    fetch('/api/panier', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lines: JSON.parse(key) }),
      signal: controller.signal,
    })
      .then(async (response) => {
        if (!response.ok) throw new Error('Réponse inattendue du serveur.');
        const cart = (await response.json()) as ResolvedCart;
        setResult({ key, cart, hasFailed: false });
      })
      .catch((error: unknown) => {
        // Une requête annulée (changement de page, panier modifié entre-temps)
        // n'est pas une erreur à signaler au client.
        if (error instanceof DOMException && error.name === 'AbortError') return;
        setResult({ key, cart: null, hasFailed: true });
      });

    return () => controller.abort();
  }, [isReady, key]);

  // Tant que le résultat en mémoire ne correspond pas au panier courant, c'est
  // qu'un appel est en vol : inutile d'un état de chargement séparé.
  const isUpToDate = result?.key === key;

  return {
    cart: isUpToDate ? result.cart : null,
    isLoading: !isReady || !isUpToDate,
    hasFailed: isUpToDate ? result.hasFailed : false,
  };
}
