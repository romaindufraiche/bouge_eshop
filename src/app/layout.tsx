import type { Metadata } from 'next';
import { Manrope, Reenie_Beanie } from 'next/font/google';
import localFont from 'next/font/local';
import { SITE_URL } from '@/lib/seo';
import { SHOP } from '@/lib/shop-config';
import './globals.css';

/**
 * Les trois typographies de la charte (page 24).
 *
 * Sun Motter est une police propriétaire, livrée avec l'identité : elle est
 * hébergée avec le site. Manrope et Reenie Beanie sont libres et servies par
 * Google Fonts, que Next.js télécharge à la compilation puis sert depuis notre
 * domaine — aucune requête vers Google côté visiteur.
 */

// Titres. Convertie de l'OTF d'origine en WOFF2 (255 Ko -> 90 Ko).
const sunMotter = localFont({
  src: '../fonts/SunMotter.woff2',
  variable: '--font-heading',
  weight: '400',
  display: 'swap',
  // Sun Motter n'a pas d'italique ni de graisses : on évite que le navigateur
  // en fabrique de synthétiques.
  adjustFontFallback: false,
});

// Sous-titres et corps de texte.
const manrope = Manrope({
  variable: '--font-body',
  subsets: ['latin'],
  display: 'swap',
});

// Notes manuscrites, à doser.
const reenieBeanie = Reenie_Beanie({
  variable: '--font-handwriting',
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
      className={`${manrope.variable} ${sunMotter.variable} ${reenieBeanie.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">{children}</body>
    </html>
  );
}
