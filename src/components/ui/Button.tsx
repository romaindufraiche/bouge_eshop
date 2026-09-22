import Link from 'next/link';
import type { ComponentProps, ReactNode } from 'react';

type Variant = 'primary' | 'accent' | 'secondary' | 'ghost';
type Size = 'md' | 'lg';

/**
 * Boutons de la boutique.
 *
 * Forme en pastille, comme les stickers et le tampon de l'identité : le
 * wordmark est très arrondi, des angles vifs jureraient. Le rayon vient du
 * jeton --radius-control, modifiable en un seul endroit.
 */
const BASE =
  'inline-flex items-center justify-center gap-2 rounded-control border text-sm font-semibold tracking-wide transition-colors disabled:cursor-not-allowed disabled:opacity-40';

const VARIANTS: Record<Variant, string> = {
  // Action principale de navigation : aplat anthracite.
  primary: 'border-ink bg-ink text-cream hover:bg-transparent hover:text-ink',
  // Action marchande (ajouter au panier, payer) : l'orange de la marque,
  // réservé à ce qui fait avancer l'achat pour qu'il garde son poids.
  // Ton assombri, le orange vif ne passant pas 4,5:1 sous du texte blanc.
  accent: 'border-accent-deep bg-accent-deep text-white hover:border-ink hover:bg-ink',
  // Action secondaire : contour seul.
  secondary: 'border-ink bg-transparent text-ink hover:bg-ink hover:text-cream',
  // Action discrète, réservée aux liens d'annulation.
  ghost:
    'border-transparent bg-transparent text-ink-soft hover:text-ink underline underline-offset-4',
};

const SIZES: Record<Size, string> = {
  md: 'px-5 py-2.5',
  lg: 'px-7 py-3.5 text-base',
};

function classes(variant: Variant, size: Size, className: string): string {
  return `${BASE} ${VARIANTS[variant]} ${SIZES[size]} ${className}`;
}

export function Button({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}: ComponentProps<'button'> & {
  children: ReactNode;
  variant?: Variant;
  size?: Size;
}) {
  return (
    <button className={classes(variant, size, className)} {...props}>
      {children}
    </button>
  );
}

export function ButtonLink({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}: ComponentProps<typeof Link> & {
  children: ReactNode;
  variant?: Variant;
  size?: Size;
}) {
  return (
    <Link className={classes(variant, size, className)} {...props}>
      {children}
    </Link>
  );
}
