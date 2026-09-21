import Link from 'next/link';
import { PageHeader } from '@/components/admin/PageHeader';
import {
  FULFILMENT,
  ORDER_STATUS,
  ORDER_STATUS_LABELS,
  type OrderStatus,
} from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { prisma } from '@/lib/prisma';
import type { Prisma } from '@prisma/client';

export const dynamic = 'force-dynamic';

export const metadata = { title: 'Commandes' };

const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

type PageProps = {
  searchParams: Promise<{ statut?: string; mode?: string }>;
};

export default async function AdminOrdersPage({ searchParams }: PageProps) {
  const params = await searchParams;

  const status =
    params.statut && params.statut in ORDER_STATUS_LABELS ? params.statut : null;
  const fulfilment =
    params.mode === FULFILMENT.DELIVERY || params.mode === FULFILMENT.PICKUP
      ? params.mode
      : null;

  const where: Prisma.OrderWhereInput = {
    ...(status
      ? { status }
      : // Par défaut on masque les commandes jamais payées : ce sont des
        // paniers abandonnés au moment du paiement, pas des commandes.
        { status: { not: ORDER_STATUS.PENDING } }),
    ...(fulfilment ? { fulfilment } : {}),
  };

  const orders = await prisma.order.findMany({
    where,
    orderBy: { createdAt: 'desc' },
    take: 200,
    select: {
      id: true,
      reference: true,
      customerName: true,
      email: true,
      status: true,
      fulfilment: true,
      totalCents: true,
      createdAt: true,
      _count: { select: { items: true } },
    },
  });

  return (
    <>
      <PageHeader
        title="Commandes"
        description="Suivez l'avancement de chaque commande, de son paiement à sa remise."
      />

      {/* --- Filtres ------------------------------------------------------- */}
      <form method="get" className="mt-8 flex flex-wrap items-end gap-3">
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
            <option value="">Toutes (sauf non payées)</option>
            {Object.entries(ORDER_STATUS_LABELS).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label htmlFor="mode" className="block text-sm font-medium">
            Mode de remise
          </label>
          <select
            id="mode"
            name="mode"
            defaultValue={fulfilment ?? ''}
            className="mt-1.5 rounded-sm border border-line bg-cream px-3 py-2 text-sm"
          >
            <option value="">Tous</option>
            <option value={FULFILMENT.DELIVERY}>Livraison</option>
            <option value={FULFILMENT.PICKUP}>Retrait</option>
          </select>
        </div>

        <button
          type="submit"
          className="rounded-sm border border-ink px-4 py-2 text-sm hover:bg-ink hover:text-cream"
        >
          Filtrer
        </button>

        {(status || fulfilment) && (
          <Link
            href="/admin/commandes"
            className="py-2 text-sm text-ink-soft underline underline-offset-4"
          >
            Réinitialiser
          </Link>
        )}
      </form>

      {/* --- Liste ---------------------------------------------------------- */}
      <p className="mt-8 text-sm text-ink-soft">
        {orders.length} commande{orders.length > 1 ? 's' : ''}.
      </p>

      {orders.length === 0 ? (
        <p className="mt-6 py-12 text-center text-ink-soft">
          Aucune commande à afficher.
        </p>
      ) : (
        <ul className="mt-4 divide-y divide-line border-y border-line">
          {orders.map((order) => (
            <li key={order.id}>
              <Link
                href={`/admin/commandes/${order.id}`}
                className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 text-sm hover:bg-sand"
              >
                <span className="w-24 shrink-0 font-medium">{order.reference}</span>

                <span className="min-w-0 flex-1">
                  <span className="block truncate">{order.customerName}</span>
                  <span className="block truncate text-xs text-ink-soft">
                    {order.email}
                  </span>
                </span>

                <span className="w-36 shrink-0 text-xs text-ink-soft">
                  {DATE_FORMAT.format(order.createdAt)}
                </span>

                <span className="w-24 shrink-0 text-xs">
                  {order.fulfilment === FULFILMENT.PICKUP ? 'Retrait' : 'Livraison'}
                  <span className="block text-ink-soft">
                    {order._count.items} article{order._count.items > 1 ? 's' : ''}
                  </span>
                </span>

                <StatusBadge status={order.status as OrderStatus} />

                <span className="w-20 shrink-0 text-right tabular-nums">
                  {formatPrice(order.totalCents)}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}

function StatusBadge({ status }: { status: OrderStatus }) {
  // Une commande à traiter doit sauter aux yeux dans la liste.
  const needsAction =
    status === ORDER_STATUS.PAID || status === ORDER_STATUS.PREPARING;

  return (
    <span
      className={`w-36 shrink-0 px-2 py-0.5 text-center text-xs ${
        needsAction
          ? 'bg-ink text-cream'
          : status === ORDER_STATUS.CANCELLED
            ? 'border border-line text-ink-soft line-through'
            : 'border border-line text-ink-soft'
      }`}
    >
      {ORDER_STATUS_LABELS[status] ?? status}
    </span>
  );
}
