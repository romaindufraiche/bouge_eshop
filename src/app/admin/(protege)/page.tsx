import Link from 'next/link';
import { PageHeader } from '@/components/admin/PageHeader';
import {
  ORDER_STATUS,
  ORDER_STATUS_LABELS,
  PRODUCT_STATUS,
  type OrderStatus,
} from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { prisma } from '@/lib/prisma';

// Le tableau de bord doit refléter l'état réel à chaque visite.
export const dynamic = 'force-dynamic';

export default async function AdminDashboardPage() {
  const [
    publishedCount,
    draftCount,
    categoryCount,
    toPrepareCount,
    recentOrders,
    lowStockVariants,
    lowStockProducts,
    paidTotal,
  ] = await Promise.all([
    prisma.product.count({ where: { status: PRODUCT_STATUS.PUBLISHED } }),
    prisma.product.count({ where: { status: PRODUCT_STATUS.DRAFT } }),
    prisma.category.count(),
    prisma.order.count({
      where: { status: { in: [ORDER_STATUS.PAID, ORDER_STATUS.PREPARING] } },
    }),
    prisma.order.findMany({
      // Les commandes jamais payées n'ont pas à encombrer le tableau de bord.
      where: { status: { not: ORDER_STATUS.PENDING } },
      orderBy: { createdAt: 'desc' },
      take: 8,
      select: {
        id: true,
        reference: true,
        customerName: true,
        status: true,
        totalCents: true,
        createdAt: true,
      },
    }),
    prisma.productVariant.findMany({
      where: { stock: { lte: 3 }, product: { status: PRODUCT_STATUS.PUBLISHED } },
      orderBy: { stock: 'asc' },
      take: 8,
      select: {
        id: true,
        size: true,
        color: true,
        stock: true,
        product: { select: { id: true, name: true } },
      },
    }),
    prisma.product.findMany({
      // Produits sans déclinaison : c'est leur stock propre qui compte.
      where: {
        status: PRODUCT_STATUS.PUBLISHED,
        stock: { lte: 3 },
        variants: { none: {} },
      },
      orderBy: { stock: 'asc' },
      take: 8,
      select: { id: true, name: true, stock: true },
    }),
    prisma.order.aggregate({
      where: { status: { not: ORDER_STATUS.CANCELLED }, paidAt: { not: null } },
      _sum: { totalCents: true },
    }),
  ]);

  return (
    <>
      <PageHeader
        title="Tableau de bord"
        description="Ce qui demande votre attention aujourd'hui."
      />

      {/* --- Chiffres clés ------------------------------------------------- */}
      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Commandes à traiter"
          value={String(toPrepareCount)}
          href="/admin/commandes"
        />
        <StatCard
          label="Produits en ligne"
          value={String(publishedCount)}
          href="/admin/produits"
        />
        <StatCard
          label="Brouillons"
          value={String(draftCount)}
          href="/admin/produits?statut=DRAFT"
        />
        <StatCard
          label="Total encaissé"
          value={formatPrice(paidTotal._sum.totalCents ?? 0)}
        />
      </div>

      <div className="mt-12 grid gap-12 lg:grid-cols-2">
        {/* --- Dernières commandes ---------------------------------------- */}
        <section>
          <div className="flex items-baseline justify-between">
            <h2 className="text-xl">Dernières commandes</h2>
            <Link
              href="/admin/commandes"
              className="text-sm underline underline-offset-4"
            >
              Tout voir
            </Link>
          </div>

          {recentOrders.length === 0 ? (
            <p className="mt-4 text-sm text-ink-soft">
              Aucune commande pour l&apos;instant.
            </p>
          ) : (
            <ul className="mt-4 divide-y divide-line border-y border-line">
              {recentOrders.map((order) => (
                <li key={order.id}>
                  <Link
                    href={`/admin/commandes/${order.id}`}
                    className="flex items-center justify-between gap-4 py-3 text-sm hover:bg-sand"
                  >
                    <span>
                      <span className="font-medium">{order.reference}</span>
                      <span className="ml-2 text-ink-soft">
                        {order.customerName}
                      </span>
                    </span>
                    <span className="flex items-center gap-3">
                      <StatusPill status={order.status as OrderStatus} />
                      <span className="tabular-nums">
                        {formatPrice(order.totalCents)}
                      </span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>

        {/* --- Stocks faibles ---------------------------------------------- */}
        <section>
          <h2 className="text-xl">Stocks faibles</h2>
          <p className="mt-1 text-sm text-ink-soft">
            Trois exemplaires ou moins, sur les produits en ligne.
          </p>

          {lowStockVariants.length === 0 && lowStockProducts.length === 0 ? (
            <p className="mt-4 text-sm text-ink-soft">
              Rien à réapprovisionner.
            </p>
          ) : (
            <ul className="mt-4 divide-y divide-line border-y border-line">
              {lowStockProducts.map((product) => (
                <li key={product.id}>
                  <Link
                    href={`/admin/produits/${product.id}`}
                    className="flex items-center justify-between gap-4 py-3 text-sm hover:bg-sand"
                  >
                    <span>{product.name}</span>
                    <StockPill stock={product.stock} />
                  </Link>
                </li>
              ))}
              {lowStockVariants.map((variant) => (
                <li key={variant.id}>
                  <Link
                    href={`/admin/produits/${variant.product.id}`}
                    className="flex items-center justify-between gap-4 py-3 text-sm hover:bg-sand"
                  >
                    <span>
                      {variant.product.name}
                      <span className="text-ink-soft">
                        {' '}
                        — {[variant.size, variant.color].filter(Boolean).join(' · ')}
                      </span>
                    </span>
                    <StockPill stock={variant.stock} />
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      <p className="mt-12 text-sm text-ink-soft">
        {categoryCount} catégorie{categoryCount > 1 ? 's' : ''} dans le
        catalogue.{' '}
        <Link href="/admin/categories" className="underline underline-offset-4">
          Les gérer
        </Link>
      </p>
    </>
  );
}

function StatCard({
  label,
  value,
  href,
}: {
  label: string;
  value: string;
  href?: string;
}) {
  const content = (
    <>
      <p className="text-xs uppercase tracking-widest text-ink-soft">{label}</p>
      <p className="mt-2 text-3xl tabular-nums">{value}</p>
    </>
  );

  if (!href) {
    return <div className="border border-line p-5">{content}</div>;
  }

  return (
    <Link href={href} className="block border border-line p-5 hover:border-ink">
      {content}
    </Link>
  );
}

function StatusPill({ status }: { status: OrderStatus }) {
  return (
    <span className="border border-line px-2 py-0.5 text-xs">
      {ORDER_STATUS_LABELS[status] ?? status}
    </span>
  );
}

function StockPill({ stock }: { stock: number }) {
  return (
    <span
      className={`rounded-control px-2 py-0.5 text-xs font-semibold tabular-nums ${
        stock === 0
          ? 'bg-accent-deep text-white'
          : 'border border-accent-deep text-accent-deep'
      }`}
    >
      {stock === 0 ? 'Épuisé' : `${stock} restant${stock > 1 ? 's' : ''}`}
    </span>
  );
}
