import type { ReactNode } from 'react';
import { ShopChrome } from '@/components/layout/ShopChrome';

/** Mise en page de toutes les pages publiques de la boutique. */
export default function BoutiqueLayout({ children }: { children: ReactNode }) {
  return <ShopChrome>{children}</ShopChrome>;
}
