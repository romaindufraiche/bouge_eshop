'use client';

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import {
  addLine,
  countItems,
  readCart,
  removeLine,
  setLineQuantity,
  writeCart,
  type CartLine,
} from '@/lib/cart';

type CartContextValue = {
  lines: CartLine[];
  /** Nombre total d'articles, toutes lignes confondues. */
  itemCount: number;
  /**
   * Faux tant que le panier du localStorage n'a pas été relu côté navigateur.
   * Le rendu serveur ne connaît pas le panier : afficher un compteur avant
   * l'hydratation provoquerait un écart entre les deux rendus.
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

export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>([]);
  const [isReady, setIsReady] = useState(false);

  // Lecture initiale, après montage uniquement.
  useEffect(() => {
    setLines(readCart());
    setIsReady(true);
  }, []);

  // Le panier reste synchronisé entre les onglets ouverts sur la boutique.
  useEffect(() => {
    function handleStorage() {
      setLines(readCart());
    }

    window.addEventListener('storage', handleStorage);
    return () => window.removeEventListener('storage', handleStorage);
  }, []);

  const persist = useCallback((next: CartLine[]) => {
    setLines(next);
    writeCart(next);
  }, []);

  const add = useCallback(
    (line: CartLine) => persist(addLine(readCart(), line)),
    [persist],
  );

  const setQuantity = useCallback(
    (target: Pick<CartLine, 'productId' | 'variantId'>, quantity: number) =>
      persist(setLineQuantity(readCart(), target, quantity)),
    [persist],
  );

  const remove = useCallback(
    (target: Pick<CartLine, 'productId' | 'variantId'>) =>
      persist(removeLine(readCart(), target)),
    [persist],
  );

  const clear = useCallback(() => persist([]), [persist]);

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
