import type { Metadata } from 'next';
import type { ReactNode } from 'react';

export const metadata: Metadata = {
  title: { default: 'Administration', template: '%s — Administration BOUGE.' },
  // L'administration ne doit jamais apparaître dans un moteur de recherche.
  robots: { index: false, follow: false },
};

/**
 * Racine de l'administration.
 *
 * Elle ne pose aucun habillage : la connexion s'affiche seule, tandis que les
 * pages protégées (groupe « protege ») ajoutent la barre de navigation.
 */
export default function AdminRootLayout({ children }: { children: ReactNode }) {
  return <div className="flex min-h-full flex-col">{children}</div>;
}
