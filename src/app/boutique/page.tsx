import type { Metadata } from 'next';
import { CategoryFilter } from '@/components/shop/CategoryFilter';
import { ProductGrid } from '@/components/shop/ProductGrid';
import { Container } from '@/components/ui/Container';
import { getCategories, getPublishedProducts } from '@/lib/queries';
import { buildMetadata } from '@/lib/seo';

// Les pages publiques sont régénérées au maximum toutes les 5 minutes :
// un prix ou une promotion modifiés apparaissent sans redéploiement.
export const revalidate = 300;

export const metadata: Metadata = buildMetadata({
  title: 'Tout le matériel de natation',
  description:
    "L'ensemble du catalogue BOUGE. : bonnets, lunettes, accessoires et vêtements de natation. Livraison en France ou retrait sur place.",
  path: '/boutique',
});

export default async function CataloguePage() {
  const [categories, products] = await Promise.all([
    getCategories(),
    getPublishedProducts(),
  ]);

  return (
    <Container size="wide">
      <div className="py-12 sm:py-16">
        <h1 className="text-4xl sm:text-5xl">Tout le matériel</h1>
        <p className="mt-3 text-ink-soft">
          {products.length} produit{products.length > 1 ? 's' : ''} en ligne.
        </p>

        <div className="mt-8">
          <CategoryFilter categories={categories} activeSlug={null} />
        </div>

        <div className="mt-12">
          <ProductGrid products={products} />
        </div>
      </div>
    </Container>
  );
}
