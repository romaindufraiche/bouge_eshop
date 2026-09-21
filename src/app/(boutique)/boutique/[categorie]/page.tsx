import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { CategoryFilter } from '@/components/shop/CategoryFilter';
import { ProductGrid } from '@/components/shop/ProductGrid';
import { Container } from '@/components/ui/Container';
import { prisma } from '@/lib/prisma';
import {
  getCategories,
  getCategoryBySlug,
  getPublishedProducts,
} from '@/lib/queries';
import { buildMetadata } from '@/lib/seo';

// Les pages publiques sont régénérées au maximum toutes les 5 minutes :
// un prix ou une promotion modifiés apparaissent sans redéploiement.
export const revalidate = 300;

type PageProps = { params: Promise<{ categorie: string }> };

/** Pré-génère une page statique par catégorie au moment du build. */
export async function generateStaticParams() {
  const categories = await prisma.category.findMany({ select: { slug: true } });
  return categories.map((category) => ({ categorie: category.slug }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { categorie } = await params;
  const category = await getCategoryBySlug(categorie);

  if (!category) {
    return { title: 'Catégorie introuvable', robots: { index: false } };
  }

  return buildMetadata({
    title: category.metaTitle ?? `${category.name} de natation`,
    description:
      category.metaDescription ??
      category.description ??
      `Tous nos produits de la catégorie ${category.name}.`,
    path: `/boutique/${category.slug}`,
  });
}

export default async function CategoryPage({ params }: PageProps) {
  const { categorie } = await params;
  const category = await getCategoryBySlug(categorie);

  if (!category) notFound();

  const [categories, products] = await Promise.all([
    getCategories(),
    getPublishedProducts({ categoryId: category.id }),
  ]);

  return (
    <Container size="wide">
      <div className="py-12 sm:py-16">
        <h1 className="text-4xl sm:text-5xl">{category.name}</h1>
        {category.description && (
          <p className="mt-3 max-w-2xl text-ink-soft">{category.description}</p>
        )}

        <div className="mt-8">
          <CategoryFilter categories={categories} activeSlug={category.slug} />
        </div>

        <div className="mt-12">
          <ProductGrid products={products} />
        </div>
      </div>
    </Container>
  );
}
