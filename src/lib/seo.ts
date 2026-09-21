import type { Metadata } from 'next';
import { SHOP } from '@/lib/shop-config';

/** URL publique du site, sans slash final. */
export const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL ?? 'http://localhost:3000'
).replace(/\/$/, '');

/**
 * Raccourcit un texte pour une balise <meta name="description">.
 * Les moteurs tronquent au-delà d'environ 160 caractères : on coupe
 * proprement sur un mot plutôt qu'au milieu.
 */
export function toMetaDescription(text: string, maxLength = 160): string {
  const clean = text.replace(/\s+/g, ' ').trim();
  if (clean.length <= maxLength) return clean;

  const cut = clean.slice(0, maxLength - 1);
  const lastSpace = cut.lastIndexOf(' ');
  return `${cut.slice(0, lastSpace > 0 ? lastSpace : cut.length)}…`;
}

/**
 * Construit les métadonnées d'une page en factorisant ce qui est commun :
 * suffixe du titre, URL canonique, OpenGraph.
 */
export function buildMetadata({
  title,
  description,
  path,
  images,
  noIndex = false,
}: {
  title: string;
  description: string;
  path: string;
  images?: string[];
  noIndex?: boolean;
}): Metadata {
  const url = `${SITE_URL}${path}`;
  const absoluteImages = images?.map((image) =>
    image.startsWith('http') ? image : `${SITE_URL}${image}`,
  );

  return {
    title,
    description: toMetaDescription(description),
    alternates: { canonical: url },
    robots: noIndex ? { index: false, follow: false } : undefined,
    openGraph: {
      type: 'website',
      locale: 'fr_FR',
      siteName: SHOP.name,
      title,
      description: toMetaDescription(description),
      url,
      images: absoluteImages,
    },
  };
}
