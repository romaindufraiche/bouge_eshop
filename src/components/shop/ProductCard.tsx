import Image from 'next/image';
import Link from 'next/link';
import { PriceTag } from '@/components/shop/PriceTag';
import { isSoldExternally, sellerName } from '@/lib/external';
import { getEffectivePrice } from '@/lib/pricing';
import type { Product, ProductImage } from '@/lib/prisma';

export type ProductCardData = Pick<
  Product,
  | 'id'
  | 'name'
  | 'slug'
  | 'priceCents'
  | 'salePriceCents'
  | 'saleStartsAt'
  | 'saleEndsAt'
  | 'externalUrl'
  | 'externalLabel'
  | 'availableInStore'
> & {
  images: Pick<ProductImage, 'url' | 'alt'>[];
  category: { name: string } | null;
};

/**
 * Vignette produit du catalogue et de la page d'accueil.
 * Toute la carte est cliquable, mais un seul lien porte le nom du produit :
 * un lecteur d'écran annonce donc une destination et non deux.
 */
export function ProductCard({
  product,
  /** `priority` sur les toutes premières vignettes visibles améliore le LCP. */
  priority = false,
}: {
  product: ProductCardData;
  priority?: boolean;
}) {
  const price = getEffectivePrice(product);
  const cover = product.images[0];
  const externe = isSoldExternally(product);

  return (
    <article className="group">
      <Link href={`/produit/${product.slug}`} className="block focus:outline-none">
        <div className="relative aspect-4/5 overflow-hidden rounded-surface bg-sand">
          {cover ? (
            <Image
              src={cover.url}
              alt={cover.alt}
              fill
              sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
              priority={priority}
              className="object-cover transition-transform duration-500 group-hover:scale-[1.03]"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-ink-soft">
              Photo à venir
            </div>
          )}

          {price.onSale && !externe && (
            <span className="absolute left-3 top-3 rounded-control bg-accent-deep px-2.5 py-1 text-xs font-semibold tracking-wide text-white">
              Promo
            </span>
          )}

          {product.availableInStore && (
            <span className="absolute right-3 top-3 rounded-control bg-ink px-2.5 py-1 text-xs font-semibold tracking-wide text-cream">
              En magasin
            </span>
          )}
        </div>

        <div className="mt-3 space-y-1">
          {product.category && (
            <p className="text-xs uppercase tracking-widest text-ink-soft">
              {product.category.name}
            </p>
          )}
          <h3 className="text-base leading-snug group-hover:underline group-hover:underline-offset-4">
            {product.name}
          </h3>
          {/* Pas de prix pour un produit vendu ailleurs : il est fixé par le
              revendeur et nous n'en avons pas la maîtrise. */}
          {externe ? (
            <p className="text-sm text-ink-soft">
              Vendu sur {sellerName(product)}
            </p>
          ) : (
            <PriceTag price={price} size="sm" />
          )}
        </div>
      </Link>
    </article>
  );
}
