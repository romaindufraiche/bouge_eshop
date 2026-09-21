'use client';

import { useEffect } from 'react';
import { useCart } from '@/components/cart/CartProvider';

/**
 * Vide le panier à l'affichage de la page de confirmation.
 * Rendu invisible : le composant n'existe que pour cet effet de bord.
 */
export function ClearCartOnMount() {
  const { clear } = useCart();

  useEffect(() => {
    clear();
    // Volontairement exécuté une seule fois, au montage : `clear` ne doit pas
    // relancer l'effet à chaque rendu.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}
