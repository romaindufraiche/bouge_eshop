import Image from 'next/image';
import Link from 'next/link';
import { Container } from '@/components/ui/Container';
import { SHIPPING, SHOP } from '@/lib/shop-config';
import { formatPrice } from '@/lib/money';

/**
 * Pied de page.
 *
 * Fond anthracite et logo crème : c'est l'une des combinaisons à fort
 * contraste retenues par la charte (page 22), et elle referme la page sans
 * ajouter de couleur supplémentaire.
 */
export function SiteFooter({
  categories,
}: {
  categories: { name: string; slug: string }[];
}) {
  const year = new Date().getFullYear();

  return (
    <footer className="mt-24 bg-ink text-cream">
      <Container size="wide">
        <div className="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            {/* Le wordmark seul : la baseline gravée dans le fichier
                devient illisible à cette taille, elle est reprise en texte
                plus bas. */}
            <Image
              src="/brand/wordmark-creme.png"
              alt={SHOP.name}
              width={720}
              height={346}
              className="h-9 w-auto"
            />
            <p className="mt-4 max-w-xs text-sm text-cream/70">{SHOP.tagline}</p>
          </div>

          <nav aria-label="Catégories">
            <h2 className="text-xs font-semibold uppercase tracking-widest text-cream/60">
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
            <h2 className="text-xs font-semibold uppercase tracking-widest text-cream/60">
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
            <h2 className="text-xs font-semibold uppercase tracking-widest text-cream/60">
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
              <li className="text-cream/70">
                {SHIPPING.freeAboveCents !== null
                  ? `Livraison offerte dès ${formatPrice(SHIPPING.freeAboveCents)}`
                  : `Livraison ${formatPrice(SHIPPING.flatRateCents)}`}
              </li>
            </ul>
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-cream/15 py-6 text-xs text-cream/60">
          <p>
            © {year} {SHOP.name} — Tous droits réservés.
          </p>
          <p className="font-note text-base text-cream/80">{SHOP.baseline}</p>
        </div>
      </Container>
    </footer>
  );
}
