'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useCart } from '@/components/cart/CartProvider';
import { Container } from '@/components/ui/Container';
import { SHOP } from '@/lib/shop-config';

export type HeaderCategory = { name: string; slug: string };

export function SiteHeader({ categories }: { categories: HeaderCategory[] }) {
  const pathname = usePathname();
  const { itemCount, isReady } = useCart();
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  // Le menu mobile se referme dès qu'on change de page.
  useEffect(() => {
    setIsMenuOpen(false);
  }, [pathname]);

  return (
    <header className="sticky top-0 z-40 border-b border-line bg-cream/95 backdrop-blur-sm">
      <Container size="wide">
        <div className="flex h-16 items-center justify-between gap-4 sm:h-20">
          <Link
            href="/"
            className="font-display text-xl tracking-tight sm:text-2xl"
            aria-label={`${SHOP.name} — accueil`}
          >
            {SHOP.name}
          </Link>

          {/* Navigation principale — masquée sur mobile au profit du menu */}
          <nav aria-label="Navigation principale" className="hidden md:block">
            <ul className="flex items-center gap-7 text-sm">
              <li>
                <NavLink href="/boutique" current={pathname}>
                  Tout le matériel
                </NavLink>
              </li>
              {categories.map((category) => (
                <li key={category.slug}>
                  <NavLink href={`/boutique/${category.slug}`} current={pathname}>
                    {category.name}
                  </NavLink>
                </li>
              ))}
            </ul>
          </nav>

          <div className="flex items-center gap-2">
            <Link
              href="/panier"
              className="px-2 py-2 text-sm hover:underline hover:underline-offset-4"
            >
              Panier
              {/* Le compteur n'apparaît qu'après lecture du panier côté
                  navigateur, pour éviter tout écart avec le rendu serveur. */}
              {isReady && itemCount > 0 && (
                <span className="ml-1.5 inline-flex min-w-5 justify-center bg-ink px-1.5 py-0.5 text-xs tabular-nums text-cream">
                  {itemCount}
                </span>
              )}
            </Link>

            <button
              type="button"
              onClick={() => setIsMenuOpen((open) => !open)}
              aria-expanded={isMenuOpen}
              aria-controls="menu-mobile"
              className="-mr-2 p-2 md:hidden"
            >
              <span className="sr-only">
                {isMenuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
              </span>
              <MenuIcon isOpen={isMenuOpen} />
            </button>
          </div>
        </div>
      </Container>

      {isMenuOpen && (
        <nav
          id="menu-mobile"
          aria-label="Navigation mobile"
          className="border-t border-line md:hidden"
        >
          <Container size="wide">
            <ul className="flex flex-col py-2">
              <li>
                <MobileLink href="/boutique">Tout le matériel</MobileLink>
              </li>
              {categories.map((category) => (
                <li key={category.slug}>
                  <MobileLink href={`/boutique/${category.slug}`}>
                    {category.name}
                  </MobileLink>
                </li>
              ))}
            </ul>
          </Container>
        </nav>
      )}
    </header>
  );
}

function NavLink({
  href,
  current,
  children,
}: {
  href: string;
  current: string;
  children: React.ReactNode;
}) {
  const isActive = current === href || current.startsWith(`${href}/`);

  return (
    <Link
      href={href}
      aria-current={isActive ? 'page' : undefined}
      className={`underline-offset-6 hover:underline ${isActive ? 'underline' : ''}`}
    >
      {children}
    </Link>
  );
}

function MobileLink({
  href,
  children,
}: {
  href: string;
  children: React.ReactNode;
}) {
  return (
    <Link href={href} className="block border-b border-line/60 py-3 text-base">
      {children}
    </Link>
  );
}

function MenuIcon({ isOpen }: { isOpen: boolean }) {
  return (
    <svg
      width="22"
      height="22"
      viewBox="0 0 22 22"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      aria-hidden="true"
    >
      {isOpen ? (
        <>
          <line x1="4" y1="4" x2="18" y2="18" />
          <line x1="18" y1="4" x2="4" y2="18" />
        </>
      ) : (
        <>
          <line x1="3" y1="6" x2="19" y2="6" />
          <line x1="3" y1="11" x2="19" y2="11" />
          <line x1="3" y1="16" x2="19" y2="16" />
        </>
      )}
    </svg>
  );
}
