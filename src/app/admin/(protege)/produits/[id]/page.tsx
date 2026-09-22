import Link from 'next/link';
import { notFound } from 'next/navigation';
import { deleteProduct } from '@/app/admin/(protege)/produits/actions';
import { ConfirmButton } from '@/components/admin/ConfirmButton';
import { PageHeader } from '@/components/admin/PageHeader';
import { ProductForm } from '@/components/admin/ProductForm';
import { ProductImages } from '@/components/admin/ProductImages';
import { PRODUCT_STATUS } from '@/lib/constants';
import { centsToEuroInput } from '@/lib/money';
import { prisma } from '@/lib/prisma';

export const dynamic = 'force-dynamic';

type PageProps = {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ enregistre?: string }>;
};

/** Format attendu par <input type="date"> : aaaa-mm-jj. */
function toDateInput(date: Date | null): string {
  return date ? date.toISOString().slice(0, 10) : '';
}

export default async function EditProductPage({ params, searchParams }: PageProps) {
  const { id } = await params;
  const { enregistre } = await searchParams;

  const [product, categories] = await Promise.all([
    prisma.product.findUnique({
      where: { id },
      include: {
        images: { orderBy: { position: 'asc' } },
        variants: { orderBy: [{ position: 'asc' }, { createdAt: 'asc' }] },
      },
    }),
    prisma.category.findMany({
      orderBy: [{ position: 'asc' }, { name: 'asc' }],
      select: { id: true, name: true },
    }),
  ]);

  if (!product) notFound();

  return (
    <>
      <PageHeader
        title={product.name}
        description={
          product.status === PRODUCT_STATUS.PUBLISHED
            ? 'Ce produit est en ligne sur la boutique.'
            : 'Ce produit est en brouillon : les clients ne le voient pas.'
        }
        action={
          product.status === PRODUCT_STATUS.PUBLISHED ? (
            <Link
              href={`/produit/${product.slug}`}
              target="_blank"
              rel="noreferrer"
              className="text-sm underline underline-offset-4"
            >
              Voir sur la boutique
            </Link>
          ) : undefined
        }
      />

      {enregistre === '1' && (
        <p role="status" className="mt-6 border-l-2 border-ink bg-sand px-4 py-3 text-sm">
          Les modifications ont été enregistrées.
        </p>
      )}

      {/* --- Photos --------------------------------------------------------
          Gérées à part du formulaire : chaque action est immédiate, il n'y a
          pas à enregistrer le produit pour qu'elle prenne effet. */}
      <section className="mt-10 border-b border-line pb-10">
        <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
          Photos
        </h2>
        <div className="mt-5">
          <ProductImages
            productId={product.id}
            images={product.images.map((image) => ({
              id: image.id,
              url: image.url,
              alt: image.alt,
            }))}
          />
        </div>
      </section>

      <ProductForm
        categories={categories}
        values={{
          id: product.id,
          name: product.name,
          slug: product.slug,
          description: product.description,
          categoryId: product.categoryId,
          price: centsToEuroInput(product.priceCents),
          salePrice:
            product.salePriceCents !== null
              ? centsToEuroInput(product.salePriceCents)
              : '',
          saleStartsAt: toDateInput(product.saleStartsAt),
          saleEndsAt: toDateInput(product.saleEndsAt),
          status: product.status,
          stock: String(product.stock),
          metaTitle: product.metaTitle ?? '',
          metaDescription: product.metaDescription ?? '',
          featured: product.featured,
          availableInStore: product.availableInStore,
          externalUrl: product.externalUrl ?? '',
          externalLabel: product.externalLabel ?? '',
          variants: product.variants.map((variant) => ({
            id: variant.id,
            size: variant.size ?? '',
            color: variant.color ?? '',
            stock: String(variant.stock),
            price:
              variant.priceCents !== null
                ? centsToEuroInput(variant.priceCents)
                : '',
          })),
        }}
      />

      {/* --- Suppression ---------------------------------------------------- */}
      <section className="mt-16 border-t border-line pt-8">
        <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
          Supprimer
        </h2>
        <p className="mt-3 max-w-2xl text-sm text-ink-soft">
          La suppression retire le produit du catalogue, avec ses photos et ses
          déclinaisons. Les commandes déjà passées restent intactes : elles
          conservent le nom et le prix pratiqués au moment de l&apos;achat.
        </p>

        <form action={deleteProduct} className="mt-4">
          <input type="hidden" name="id" value={product.id} />
          <ConfirmButton
            label="Supprimer ce produit"
            title={`Supprimer « ${product.name} » ?`}
            message="Cette action est définitive. Le produit, ses photos et ses déclinaisons seront effacés."
          />
        </form>
      </section>
    </>
  );
}
