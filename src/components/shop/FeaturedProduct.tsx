import Image from 'next/image';
import Link from 'next/link';
import { PriceTag } from '@/components/shop/PriceTag';
import { ButtonLink } from '@/components/ui/Button';
import { Container } from '@/components/ui/Container';
import { isSoldExternally, sellerName } from '@/lib/external';
import { getEffectivePrice } from '@/lib/pricing';
import type { ProductCardData } from '@/components/shop/ProductCard';

export type FeaturedProductData = ProductCardData & {
  description: string;
};

/**
 * Mise en avant d'un produit en haut de la page d'accueil.
 *
 * Le bouton s'adapte : vers le revendeur pour un produit vendu ailleurs,
 * vers la fiche pour un produit de la boutique.
 */
export function FeaturedProduct({ product }: { product: FeaturedProductData }) {
  const price = getEffectivePrice(product);
  const externe = isSoldExternally(product);
  const cover = product.images[0];

  return (
    <section className="border-b border-line bg-sand">
      <Container size="wide">
        <div className="grid items-center gap-10 py-14 sm:py-16 lg:grid-cols-2 lg:gap-16">
          <div className="relative aspect-4/3 overflow-hidden rounded-surface bg-cream lg:aspect-square">
            {cover ? (
              <Image
                src={cover.url}
                alt={cover.alt}
                fill
                sizes="(max-width: 1024px) 100vw, 50vw"
                className="object-cover"
              />
            ) : (
              <div className="flex h-full items-center justify-center text-sm text-ink-soft">
                Photo à venir
              </div>
            )}
          </div>

          <div>
            <p className="text-xs font-semibold uppercase tracking-widest text-accent-deep">
              La sélection du moment
            </p>

            <h2 className="mt-3 text-3xl sm:text-4xl lg:text-5xl">
              {product.name}
            </h2>

            <p className="mt-5 max-w-md whitespace-pre-line text-ink-soft">
              {product.description}
            </p>

            <div className="mt-6 flex flex-wrap items-center gap-4">
              {externe ? (
                <p className="text-ink-soft">Vendu sur {sellerName(product)}</p>
              ) : (
                <PriceTag price={price} size="lg" />
              )}

              {product.availableInStore && (
                <span className="rounded-control bg-ink px-3 py-1 text-xs font-semibold tracking-wide text-cream">
                  Disponible en magasin
                </span>
              )}
            </div>

            <div className="mt-8 flex flex-wrap gap-3">
              {externe && product.externalUrl ? (
                <a
                  href={product.externalUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center justify-center gap-2 rounded-control border border-accent-deep bg-accent-deep px-7 py-3.5 text-base font-semibold tracking-wide text-white transition-colors hover:border-ink hover:bg-ink"
                >
                  Acheter sur {sellerName(product)}
                  <span aria-hidden="true">→</span>
                  <span className="sr-only">(nouvel onglet)</span>
                </a>
              ) : (
                <ButtonLink
                  href={`/produit/${product.slug}`}
                  variant="accent"
                  size="lg"
                >
                  Voir le produit
                </ButtonLink>
              )}

              <Link
                href={`/produit/${product.slug}`}
                className="self-center text-sm underline underline-offset-4"
              >
                {externe ? 'En savoir plus' : 'Toutes les informations'}
              </Link>
            </div>
          </div>
        </div>
      </Container>
    </section>
  );
}
