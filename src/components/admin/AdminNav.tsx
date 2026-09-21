'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const LINKS = [
  { href: '/admin', label: 'Tableau de bord', exact: true },
  { href: '/admin/produits', label: 'Produits' },
  { href: '/admin/categories', label: 'Catégories' },
  { href: '/admin/commandes', label: 'Commandes' },
];

export function AdminNav() {
  const pathname = usePathname();

  return (
    <nav aria-label="Navigation de l'administration">
      <ul className="flex flex-wrap gap-1">
        {LINKS.map((link) => {
          const isActive = link.exact
            ? pathname === link.href
            : pathname.startsWith(link.href);

          return (
            <li key={link.href}>
              <Link
                href={link.href}
                aria-current={isActive ? 'page' : undefined}
                className={`inline-block border-b-2 px-3 py-2 text-sm transition-colors ${
                  isActive
                    ? 'border-ink font-medium'
                    : 'border-transparent text-ink-soft hover:text-ink'
                }`}
              >
                {link.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
