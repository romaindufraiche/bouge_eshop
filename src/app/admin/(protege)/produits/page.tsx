import Image from 'next/image';
import Link from 'next/link';
import { PageHeader } from '@/components/admin/PageHeader';
import { ButtonLink } from '@/components/ui/Button';
import { PRODUCT_STATUS, PRODUCT_STATUS_LABELS, type ProductStatus } from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { prisma } from '@/lib/prisma';
import { getEffectivePrice } from '@/lib/pricing';
import type { Prisma } from '@prisma/client';

export const dynamic = 'force-dynamic';

/** Tris proposés, et clause Prisma correspondante. */
const SORTS = {
  recent: { label: 'Plus récents', orderBy: { createdAt: 'desc' } },
  nom: { label: 'Nom (A → Z)', orderBy: { name: 'asc' } },
  'prix-asc': { label: 'Prix croissant', orderBy: { priceCents: 'asc' } },
  'prix-desc': { label: 'Prix décroissant', orderBy: { priceCents: 'desc' } },
  stock: { label: 'Stock croissant', orderBy: { stock: 'asc' } },
} satisfies Record<
  string,
  { label: string; orderBy: Prisma.ProductOrderByWithRelationInput }
>;

type SortKey = keyof typeof SORTS;

type PageProps = {
  searchParams: Promise<{
    recherche?: string;
    statut?: string;
    tri?: string;
    supprime?: string;
  }>;
};

export default async function AdminProductsPage({ searchParams }: PageProps) {
  const params = await searchParams;

  const search = (params.recherche ?? '').trim();
  const status = params.statut === 'DRAFT' || params.statut === 'PUBLISHED'
    ? params.statut
    : null;
  const sortKey: SortKey = (params.tri as SortKey) in SORTS
    ? (params.tri as SortKey)
    : 'recent';

  const where: Prisma.ProductWhereInput = {
    ...(status ? { status } : {}),
    // SQLite ne gère pas `mode: 'insensitive'` ; la recherche est donc
    // sensible à la casse en développement, et insensible sur PostgreSQL.
    ...(search ? { name: { contains: search } } : {}),
  };

  const [products, categories] = await Promise.all([
    prisma.product.findMany({
      where,
      orderBy: SORTS[sortKey].orderBy,
      include: {
        category: { select: { name: true } },
        images: { orderBy: { position: 'asc' }, take: 1 },
        variants: { select: { stock: true } },
      },
    }),
    prisma.category.count(),
  ]);

  return (
    <>
      <PageHeader
        title="Produits"
        description="Ajoutez, modifiez ou retirez les articles du catalogue."
        action={
          categories > 0 ? (
            <ButtonLink href="/admin/produits/nouveau">Ajouter un produit</ButtonLink>
          ) : undefined
        }
      />

      {params.supprime === '1' && (
        <p role="status" className="mt-6 border-l-2 border-ink bg-sand px-4 py-3 text-sm">
          Le produit a été supprimé.
        </p>
      )}

      {categories === 0 && (
        <p className="mt-6 border-l-2 border-accent bg-sand px-4 py-3 text-sm">
          Créez d&apos;abord au moins une catégorie :{' '}
          <Link href="/admin/categories" className="underline underline-offset-4">
            gérer les catégories
          </Link>
          .
        </p>
      )}

      {/* --- Recherche et tri --------------------------------------------- */}
      <form method="get" className="mt-8 flex flex-wrap items-end gap-3">
        <div>
          <label htmlFor="recherche" className="block text-sm font-medium">
            Rechercher
          </label>
          <input
            id="recherche"
            type="search"
            name="recherche"
            defaultValue={search}
            placeholder="Nom du produit"
            className="mt-1.5 w-56 rounded-sm border border-line bg-cream px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label htmlFor="statut" className="block text-sm font-medium">
            Statut
          </label>
          <select
            id="statut"
            name="statut"
            defaultValue={status ?? ''}
            className="mt-1.5 rounded-sm border border-line bg-cream px-3 py-2 text-sm"
          >
            <option value="">Tous</option>
            <option value={PRODUCT_STATUS.PUBLISHED}>En ligne</option>
            <option value={PRODUCT_STATUS.DRAFT}>Brouillon</option>
          </select>
        </div>

        <div>
          <label htmlFor="tri" className="block text-sm font-medium">
            Trier par
          </label>
          <select
            id="tri"
            name="tri"
            defaultValue={sortKey}
            className="mt-1.5 rounded-sm border border-line bg-cream px-3 py-2 text-sm"
          >
            {Object.entries(SORTS).map(([key, sort]) => (
              <option key={key} value={key}>
                {sort.label}
              </option>
            ))}
          </select>
        </div>

        <button
          type="submit"
          className="rounded-sm border border-ink px-4 py-2 text-sm hover:bg-ink hover:text-cream"
        >
          Appliquer
        </button>

        {(search || status || sortKey !== 'recent') && (
          <Link
            href="/admin/produits"
            className="py-2 text-sm text-ink-soft underline underline-offset-4"
          >
            Réinitialiser
          </Link>
        )}
      </form>

      {/* --- Liste --------------------------------------------------------- */}
      <p className="mt-8 text-sm text-ink-soft">
        {products.length} produit{products.length > 1 ? 's' : ''}
        {search && ` correspondant à « ${search} »`}.
      </p>

      {products.length === 0 ? (
        <p className="mt-6 py-12 text-center text-ink-soft">
          Aucun produit à afficher.
        </p>
      ) : (
        <ul className="mt-4 divide-y divide-line border-y border-line">
          {products.map((product) => {
            const price = getEffectivePrice(product);
            const totalStock =
              product.variants.length > 0
                ? product.variants.reduce((sum, variant) => sum + variant.stock, 0)
                : product.stock;

            return (
              <li key={product.id}>
                <Link
                  href={`/admin/produits/${product.id}`}
                  className="flex items-center gap-4 py-3 hover:bg-sand"
                >
                  <span className="relative aspect-square w-12 shrink-0 overflow-hidden bg-sand">
                    {product.images[0] && (
                      <Image
                        src={product.images[0].url}
                        alt=""
                        fill
                        sizes="48px"
                        className="object-cover"
                      />
                    )}
                  </span>

                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">
                      {product.name}
                    </span>
                    <span className="block text-xs text-ink-soft">
                      {product.category.name}
                      {product.variants.length > 0 &&
                        ` · ${product.variants.length} déclinaison${product.variants.length > 1 ? 's' : ''}`}
                    </span>
                  </span>

                  <span className="hidden w-28 shrink-0 text-sm tabular-nums sm:block">
                    {formatPrice(price.cents)}
                    {price.onSale && (
                      <span className="block text-xs text-accent-deep">en promo</span>
                    )}
                  </span>

                  <span className="w-20 shrink-0 text-sm tabular-nums">
                    <span className={totalStock === 0 ? 'text-accent-deep' : ''}>
                      {totalStock}
                    </span>
                    <span className="block text-xs text-ink-soft">en stock</span>
                  </span>

                  <StatusBadge status={product.status as ProductStatus} />
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}

function StatusBadge({ status }: { status: ProductStatus }) {
  const isPublished = status === PRODUCT_STATUS.PUBLISHED;

  return (
    <span
      className={`w-24 shrink-0 px-2 py-0.5 text-center text-xs ${
        isPublished ? 'bg-ink text-cream' : 'border border-line text-ink-soft'
      }`}
    >
      {PRODUCT_STATUS_LABELS[status]}
    </span>
  );
}
