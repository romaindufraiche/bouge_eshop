import Link from 'next/link';
import type { ComponentProps, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost';
type Size = 'md' | 'lg';

const BASE =
  'inline-flex items-center justify-center gap-2 rounded-sm border text-sm font-medium tracking-wide transition-colors disabled:cursor-not-allowed disabled:opacity-40';

const VARIANTS: Record<Variant, string> = {
  // Action principale : aplat encre sur fond crème.
  primary:
    'border-ink bg-ink text-cream hover:bg-transparent hover:text-ink',
  // Action secondaire : contour seul.
  secondary:
    'border-ink bg-transparent text-ink hover:bg-ink hover:text-cream',
  // Action discrète : ni fond ni contour, réservée aux liens d'annulation.
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
