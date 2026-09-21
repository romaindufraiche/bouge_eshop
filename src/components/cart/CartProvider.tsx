'use client';

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from 'react';
import {
  addLine,
  countItems,
  removeLine,
  setLineQuantity,
  type CartLine,
} from '@/lib/cart';
import { cartStore } from '@/lib/cart-store';

type CartContextValue = {
  lines: CartLine[];
  /** Nombre total d'articles, toutes lignes confondues. */
  itemCount: number;
  /**
   * Faux pendant le rendu serveur et l'hydratation, vrai ensuite.
   * Le serveur ne connaît pas le panier : afficher un compteur avant
   * l'hydratation créerait un écart entre les deux rendus.
   */
  isReady: boolean;
  add: (line: CartLine) => void;
  setQuantity: (
    target: Pick<CartLine, 'productId' | 'variantId'>,
    quantity: number,
  ) => void;
  remove: (target: Pick<CartLine, 'productId' | 'variantId'>) => void;
  clear: () => void;
};

const CartContext = createContext<CartContextValue | null>(null);

// Déclarées hors du composant pour garder une référence stable d'un rendu à
// l'autre, comme `useSyncExternalStore` l'exige.
const subscribe = cartStore.subscribe;
const getSnapshot = () => cartStore.getSnapshot();
const getServerSnapshot = () => cartStore.getServerSnapshot();
const alwaysReady = () => true;
const neverReadyOnServer = () => false;

export function CartProvider({ children }: { children: ReactNode }) {
  const lines = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  const isReady = useSyncExternalStore(
    subscribe,
    alwaysReady,
    neverReadyOnServer,
  );

  // Chaque modification repart de la valeur stockée, et non de celle capturée
  // au rendu : deux ajouts rapprochés ne s'écrasent donc pas.
  const add = useCallback(
    (line: CartLine) => cartStore.set(addLine(cartStore.read(), line)),
    [],
  );

  const setQuantity = useCallback(
    (target: Pick<CartLine, 'productId' | 'variantId'>, quantity: number) =>
      cartStore.set(setLineQuantity(cartStore.read(), target, quantity)),
    [],
  );

  const remove = useCallback(
    (target: Pick<CartLine, 'productId' | 'variantId'>) =>
      cartStore.set(removeLine(cartStore.read(), target)),
    [],
  );

  const clear = useCallback(() => cartStore.set([]), []);

  const value = useMemo<CartContextValue>(
    () => ({
      lines,
      itemCount: countItems(lines),
      isReady,
      add,
      setQuantity,
      remove,
      clear,
    }),
    [lines, isReady, add, setQuantity, remove, clear],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error(
      "useCart() doit être appelé à l'intérieur d'un <CartProvider>.",
    );
  }
  return context;
}
