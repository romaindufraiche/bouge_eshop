import type { ReactNode } from 'react';

/**
 * Colonne de contenu centrée.
 *
 * `size` règle la largeur maximale : « wide » pour les grilles produits,
 * « default » pour les pages courantes, « narrow » pour les textes longs
 * (mentions légales, CGV) où une ligne trop large devient pénible à lire.
 */
export function Container({
  children,
  size = 'default',
  className = '',
}: {
  children: ReactNode;
  size?: 'narrow' | 'default' | 'wide';
  className?: string;
}) {
  const maxWidth = {
    narrow: 'max-w-2xl',
    default: 'max-w-5xl',
    wide: 'max-w-7xl',
  }[size];

  return (
    <div className={`mx-auto w-full ${maxWidth} px-5 sm:px-8 ${className}`}>
      {children}
    </div>
  );
}
