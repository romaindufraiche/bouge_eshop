import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { ProductGallery } from '@/components/shop/ProductGallery';
import {
  ProductPurchase,
  type PurchasableVariant,
} from '@/components/shop/ProductPurchase';
import { ProductCard } from '@/components/shop/ProductCard';
import { Container } from '@/components/ui/Container';
import { PRODUCT_STATUS } from '@/lib/constants';
import { prisma } from '@/lib/prisma';
import { getEffectivePrice, getVariantPrice } from '@/lib/pricing';
import { getPublishedProductBySlug, getRelatedProducts } from '@/lib/queries';
import { buildMetadata, SITE_URL, toMetaDescription } from '@/lib/seo';
import { SHIPPING, SHOP } from '@/lib/shop-config';
import { formatPrice } from '@/lib/money';

// Les pages publiques sont régénérées au maximum toutes les 5 minutes :
// un prix ou une promotion modifiés apparaissent sans redéploiement.
export const revalidate = 300;

type PageProps = { params: Promise<{ slug: string }> };

/** Pré-génère une page statique par produit publié au moment du build. */
export async function generateStaticParams() {
  const products = await prisma.product.findMany({
    where: { status: PRODUCT_STATUS.PUBLISHED },
    select: { slug: true },
  });
  return products.map((product) => ({ slug: product.slug }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = await getPublishedProductBySlug(slug);

  if (!product) {
    return { title: 'Produit introuvable', robots: { index: false } };
  }

  return buildMetadata({
    title: product.metaTitle ?? product.name,
    description: product.metaDescription ?? product.description,
    path: `/produit/${product.slug}`,
    images: product.images.slice(0, 1).map((image) => image.url),
  });
}

export default async function ProductPage({ params }: PageProps) {
  const { slug } = await params;
  const product = await getPublishedProductBySlug(slug);

  if (!product) notFound();

  const basePrice = getEffectivePrice(product);

  // Les prix sont calculés ici, côté serveur : le composant client se contente
  // de les afficher, sans risque d'écart dû à l'horloge du visiteur.
  const variants: PurchasableVariant[] = product.variants.map((variant) => ({
    id: variant.id,
    size: variant.size,
    color: variant.color,
    stock: variant.stock,
    price: getVariantPrice(product, variant),
  }));

  const related = await getRelatedProducts(product.categoryId, product.id);

  const totalStock =
    variants.length > 0
      ? variants.reduce((sum, variant) => sum + variant.stock, 0)
      : product.stock;

  // Données structurées : elles permettent à Google d'afficher prix et
  // disponibilité directement dans les résultats de recherche.
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: toMetaDescription(product.description, 500),
    sku: product.id,
    brand: { '@type': 'Brand', name: SHOP.name },
    image: product.images.map((image) => `${SITE_URL}${image.url}`),
    offers: {
      '@type': 'Offer',
      url: `${SITE_URL}/produit/${product.slug}`,
      priceCurrency: 'EUR',
      price: (basePrice.cents / 100).toFixed(2),
      availability:
        totalStock > 0
          ? 'https://schema.org/InStock'
          : 'https://schema.org/OutOfStock',
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />

      <Container size="wide">
        <div className="py-8 sm:py-12">
          {/* Fil d'Ariane */}
          <nav aria-label="Fil d'Ariane" className="text-sm text-ink-soft">
            <ol className="flex flex-wrap items-center gap-2">
              <li>
                <Link href="/boutique" className="hover:underline hover:underline-offset-4">
                  Boutique
                </Link>
              </li>
              <li aria-hidden="true">/</li>
              <li>
                <Link
                  href={`/boutique/${product.category.slug}`}
                  className="hover:underline hover:underline-offset-4"
                >
                  {product.category.name}
                </Link>
              </li>
              <li aria-hidden="true">/</li>
              <li aria-current="page" className="text-ink">
                {product.name}
              </li>
            </ol>
          </nav>

          <div className="mt-8 grid gap-10 lg:grid-cols-2 lg:gap-16">
            <ProductGallery images={product.images} productName={product.name} />

            <div>
              <p className="text-xs uppercase tracking-widest text-ink-soft">
                {product.category.name}
              </p>
              <h1 className="mt-2 text-3xl sm:text-4xl">{product.name}</h1>

              <div className="mt-6">
                <ProductPurchase
                  productId={product.id}
                  basePrice={basePrice}
                  baseStock={product.stock}
                  variants={variants}
                />
              </div>

              <div className="mt-10 border-t border-line pt-6">
                <h2 className="font-sans text-sm font-medium">Description</h2>
                {/* `whitespace-pre-line` conserve les retours à la ligne saisis
                    dans l'admin sans autoriser de HTML dans la description. */}
                <p className="mt-2 whitespace-pre-line text-ink-soft">
                  {product.description}
                </p>
              </div>

              <div className="mt-6 border-t border-line pt-6 text-sm text-ink-soft">
                <p>
                  Livraison en France {formatPrice(SHIPPING.flatRateCents)}
                  {SHIPPING.freeAboveCents !== null &&
                    `, offerte dès ${formatPrice(SHIPPING.freeAboveCents)}`}
                  . Retrait sur place sans frais.
                </p>
              </div>
            </div>
          </div>

          {related.length > 0 && (
            <section className="mt-20 border-t border-line pt-12">
              <h2 className="text-2xl sm:text-3xl">À voir aussi</h2>
              <ul className="mt-8 grid grid-cols-2 gap-x-5 gap-y-10 lg:grid-cols-4">
                {related.map((item) => (
                  <li key={item.id}>
                    <ProductCard product={item} />
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      </Container>
    </>
  );
}
