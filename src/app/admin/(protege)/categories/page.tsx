import { CategoryManager } from '@/components/admin/CategoryManager';
import { PageHeader } from '@/components/admin/PageHeader';
import { prisma } from '@/lib/prisma';

export const dynamic = 'force-dynamic';

export const metadata = { title: 'Catégories' };

export default async function AdminCategoriesPage() {
  const categories = await prisma.category.findMany({
    orderBy: [{ position: 'asc' }, { name: 'asc' }],
    select: {
      id: true,
      name: true,
      slug: true,
      description: true,
      _count: { select: { products: true } },
    },
  });

  return (
    <>
      <PageHeader
        title="Catégories"
        description="Les rayons de la boutique. Chaque produit appartient à une catégorie."
      />

      <div className="mt-8">
        <CategoryManager
          categories={categories.map((category) => ({
            id: category.id,
            name: category.name,
            slug: category.slug,
            description: category.description ?? '',
            productCount: category._count.products,
          }))}
        />
      </div>
    </>
  );
}
