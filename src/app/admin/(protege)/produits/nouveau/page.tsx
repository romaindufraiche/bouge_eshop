import Link from 'next/link';
import { PageHeader } from '@/components/admin/PageHeader';
import { ProductForm } from '@/components/admin/ProductForm';
import { PRODUCT_STATUS } from '@/lib/constants';
import { prisma } from '@/lib/prisma';

export const dynamic = 'force-dynamic';

export const metadata = { title: 'Nouveau produit' };

export default async function NewProductPage() {
  const categories = await prisma.category.findMany({
    orderBy: [{ position: 'asc' }, { name: 'asc' }],
    select: { id: true, name: true },
  });

  if (categories.length === 0) {
    return (
      <>
        <PageHeader title="Nouveau produit" />
        <p className="mt-8 border-l-2 border-accent bg-sand px-4 py-3 text-sm">
          Créez d&apos;abord une catégorie : chaque produit doit être rangé
          quelque part.{' '}
          <Link href="/admin/categories" className="underline underline-offset-4">
            Gérer les catégories
          </Link>
        </p>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Nouveau produit"
        description="Enregistrez le produit pour pouvoir ensuite y ajouter des photos."
      />

      <ProductForm
        categories={categories}
        values={{
          id: '',
          name: '',
          slug: '',
          description: '',
          categoryId: '',
          price: '',
          salePrice: '',
          saleStartsAt: '',
          saleEndsAt: '',
          // Un nouveau produit part en brouillon : on ne publie pas par
          // accident un article incomplet.
          status: PRODUCT_STATUS.DRAFT,
          stock: '0',
          metaTitle: '',
          metaDescription: '',
          featured: false,
          availableInStore: false,
          externalUrl: '',
          externalLabel: '',
          variants: [],
        }}
      />
    </>
  );
}
