import type { Metadata } from 'next';
import { Inter, Instrument_Serif } from 'next/font/google';
import { SITE_URL } from '@/lib/seo';
import { SHOP } from '@/lib/shop-config';
import './globals.css';

// Typographies provisoires, en attente des fichiers du dossier FONTS de la
// charte. Elles sont exposées en variables CSS : les remplacer ne demande de
// toucher qu'à ce fichier et à globals.css.
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
    'Bonnets, lunettes, accessoires et vêtements de natation. Livraison en France ou retrait sur place.',
  openGraph: {
    type: 'website',
    locale: 'fr_FR',
    siteName: SHOP.name,
  },
};

/**
 * Mise en page racine : elle ne pose que le document et les typographies.
 * L'habillage boutique vit dans le groupe (boutique), l'administration a le
 * sien : les deux ne partagent aucun en-tête.
 */
export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html
      lang="fr"
      className={`${body.variable} ${heading.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">{children}</body>
    </html>
  );
}
