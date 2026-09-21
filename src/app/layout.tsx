import type { Metadata } from 'next';
import { Inter, Instrument_Serif } from 'next/font/google';
import { CartProvider } from '@/components/cart/CartProvider';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { SiteHeader } from '@/components/layout/SiteHeader';
import { getCategories } from '@/lib/queries';
import { SITE_URL } from '@/lib/seo';
import { SHOP } from '@/lib/shop-config';
import './globals.css';

// Typographies provisoires, en attente des fichiers du dossier FONTS de la
// charte. Elles sont exposées en variables CSS : les remplacer ne demande
// de toucher qu'à ce fichier et à globals.css.
const body = Inter({
  variable: '--font-body',
  subsets: ['latin'],
  display: 'swap',
});

const heading = Instrument_Serif({
  variable: '--font-heading',
  subsets: ['latin'],
  weight: '400',
  display: 'swap',
});

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: `${SHOP.name} — ${SHOP.tagline}`,
    // Les pages n'ont qu'à déclarer leur titre court : le suffixe est ajouté ici.
    template: `%s — ${SHOP.name}`,
  },
  description:
    "Bonnets, lunettes, accessoires et vêtements de natation. Livraison en France ou retrait sur place.",
  openGraph: {
    type: 'website',
    locale: 'fr_FR',
    siteName: SHOP.name,
  },
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const categories = await getCategories();

  return (
    <html
      lang="fr"
      className={`${body.variable} ${heading.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">
        <CartProvider>
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
        </CartProvider>
      </body>
    </html>
  );
}
