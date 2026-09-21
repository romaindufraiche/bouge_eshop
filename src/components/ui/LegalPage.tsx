import type { ReactNode } from 'react';
import { Container } from '@/components/ui/Container';

/**
 * Gabarit des pages de contenu éditorial (mentions légales, CGV, livraison).
 * Colonne étroite : une ligne trop large devient vite pénible à lire.
 */
export function LegalPage({
  title,
  updatedAt,
  children,
}: {
  title: string;
  updatedAt?: string;
  children: ReactNode;
}) {
  return (
    <Container size="narrow">
      <article className="py-12 sm:py-16">
        <h1 className="text-4xl sm:text-5xl">{title}</h1>
        {updatedAt && (
          <p className="mt-3 text-sm text-ink-soft">
            Dernière mise à jour : {updatedAt}
          </p>
        )}
        <div className="mt-10 space-y-8 text-ink-soft [&_h2]:font-sans [&_h2]:text-base [&_h2]:font-medium [&_h2]:text-ink [&_p]:mt-2 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5">
          {children}
        </div>
      </article>
    </Container>
  );
}

/**
 * Encadré signalant un contenu qui reste à rédiger.
 * Volontairement visible : il ne doit pas passer inaperçu à la mise en ligne.
 */
export function ToComplete({ children }: { children: ReactNode }) {
  return (
    <p className="border-l-2 border-accent bg-sand px-4 py-3 text-sm text-ink">
      <strong className="font-medium">À compléter avant la mise en ligne.</strong>{' '}
      {children}
    </p>
  );
}
