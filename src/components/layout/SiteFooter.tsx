import Link from 'next/link';
import { Container } from '@/components/ui/Container';
import { SHIPPING, SHOP } from '@/lib/shop-config';
import { formatPrice } from '@/lib/money';

export function SiteFooter({
  categories,
}: {
  categories: { name: string; slug: string }[];
}) {
  const year = new Date().getFullYear();

  return (
    <footer className="mt-24 border-t border-line">
      <Container size="wide">
        <div className="grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <p className="font-display text-xl tracking-tight">{SHOP.name}</p>
            <p className="mt-2 max-w-xs text-sm text-ink-soft">{SHOP.tagline}</p>
          </div>

          <nav aria-label="Catégories">
            <h2 className="text-xs uppercase tracking-widest text-ink-soft">
              Catalogue
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              {categories.map((category) => (
                <li key={category.slug}>
                  <Link
                    href={`/boutique/${category.slug}`}
                    className="hover:underline hover:underline-offset-4"
                  >
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <nav aria-label="Informations">
            <h2 className="text-xs uppercase tracking-widest text-ink-soft">
              Informations
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              <li>
                <Link
                  href="/livraison"
                  className="hover:underline hover:underline-offset-4"
                >
                  Livraison et retrait
                </Link>
              </li>
              <li>
                <Link href="/cgv" className="hover:underline hover:underline-offset-4">
                  Conditions générales de vente
                </Link>
              </li>
              <li>
                <Link
                  href="/mentions-legales"
                  className="hover:underline hover:underline-offset-4"
                >
                  Mentions légales
                </Link>
              </li>
            </ul>
          </nav>

          <div>
            <h2 className="text-xs uppercase tracking-widest text-ink-soft">
              Contact
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              <li>
                <a
                  href={`mailto:${SHOP.email}`}
                  className="hover:underline hover:underline-offset-4"
                >
                  {SHOP.email}
                </a>
              </li>
              {SHOP.phone && <li>{SHOP.phone}</li>}
              <li className="text-ink-soft">
                {SHIPPING.freeAboveCents !== null
                  ? `Livraison offerte dès ${formatPrice(SHIPPING.freeAboveCents)}`
                  : `Livraison ${formatPrice(SHIPPING.flatRateCents)}`}
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-line py-6 text-xs text-ink-soft">
          © {year} {SHOP.name} — Tous droits réservés.
        </div>
      </Container>
    </footer>
  );
}
