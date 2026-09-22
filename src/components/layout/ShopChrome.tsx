import type { ReactNode } from 'react';
import { CartProvider } from '@/components/cart/CartProvider';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { SiteHeader } from '@/components/layout/SiteHeader';
import { getCategories } from '@/lib/queries';

/**
 * Habillage des pages publiques : en-tête, pied de page et panier.
 *
 * Extrait de la mise en page du groupe « boutique » pour être réutilisé par la
 * page 404 globale, qui vit hors de ce groupe mais doit ressembler au reste du
 * site.
 */
export async function ShopChrome({ children }: { children: ReactNode }) {
  const categories = await getCategories();

  return (
    // `data-brand` délimite la boutique : c'est ce qui déclenche la
    // typographie d'affichage de la marque, que l'administration n'utilise pas.
    <CartProvider>
      <div data-brand className="flex flex-1 flex-col">
        {/* Permet d'atteindre le contenu directement au clavier. */}
        <a
          href="#contenu"
          className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-4 focus:py-2 focus:text-cream"
        >
          Aller au contenu
        </a>

        <SiteHeader categories={categories} />
        <main id="contenu" className="flex-1">
          {children}
        </main>
        <SiteFooter categories={categories} />
      </div>
    </CartProvider>
  );
}
