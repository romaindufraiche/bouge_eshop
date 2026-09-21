import Link from 'next/link';
import { notFound } from 'next/navigation';
import {
  updateAdminNote,
  updateOrderStatus,
} from '@/app/admin/(protege)/commandes/actions';
import { PageHeader } from '@/components/admin/PageHeader';
import { Button } from '@/components/ui/Button';
import {
  availableOrderStatuses,
  FULFILMENT,
  FULFILMENT_LABELS,
  ORDER_STATUS,
  ORDER_STATUS_LABELS,
  type OrderStatus,
} from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { prisma } from '@/lib/prisma';

export const dynamic = 'force-dynamic';

const DATE_FORMAT = new Intl.DateTimeFormat('fr-FR', {
  dateStyle: 'long',
  timeStyle: 'short',
});

type PageProps = { params: Promise<{ id: string }> };

export default async function AdminOrderDetailPage({ params }: PageProps) {
  const { id } = await params;

  const order = await prisma.order.findUnique({
    where: { id },
    include: { items: true, pickupPoint: true },
  });

  if (!order) notFound();

  const isPickup = order.fulfilment === FULFILMENT.PICKUP;
  const statuses = availableOrderStatuses(order.fulfilment);

  return (
    <>
      <PageHeader
        title={`Commande ${order.reference}`}
        description={`Passée le ${DATE_FORMAT.format(order.createdAt)}${
          order.paidAt ? `, payée le ${DATE_FORMAT.format(order.paidAt)}` : ''
        }.`}
        action={
          <Link
            href="/admin/commandes"
            className="text-sm underline underline-offset-4"
          >
            Retour à la liste
          </Link>
        }
      />

      {order.status === ORDER_STATUS.PENDING && (
        <p className="mt-6 border-l-2 border-accent bg-sand px-4 py-3 text-sm">
          <strong className="font-medium">Paiement non confirmé.</strong> Le
          client a quitté le tunnel avant de payer, ou le paiement a échoué.
          Aucun stock n&apos;a été décompté.
        </p>
      )}

      <div className="mt-10 grid gap-12 lg:grid-cols-[1fr_20rem]">
        <div className="space-y-12">
          {/* --- Articles --------------------------------------------------- */}
          <section>
            <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
              Articles
            </h2>

            <ul className="mt-4 divide-y divide-line border-y border-line">
              {order.items.map((item) => (
                <li key={item.id} className="flex justify-between gap-4 py-3 text-sm">
                  <span className="min-w-0">
                    <span className="block">
                      {item.productName}
                      {item.variantLabel && (
                        <span className="text-ink-soft"> — {item.variantLabel}</span>
                      )}
                    </span>
                    <span className="block text-xs text-ink-soft">
                      {formatPrice(item.unitPriceCents)} × {item.quantity}
                      {/* Le produit a pu être supprimé du catalogue depuis. */}
                      {item.productId === null && ' · produit retiré du catalogue'}
                    </span>
                  </span>
                  <span className="shrink-0 tabular-nums">
                    {formatPrice(item.lineTotalCents)}
                  </span>
                </li>
              ))}
            </ul>

            <dl className="mt-4 space-y-1 text-sm">
              <div className="flex justify-between">
                <dt className="text-ink-soft">Sous-total</dt>
                <dd className="tabular-nums">{formatPrice(order.subtotalCents)}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-ink-soft">Frais de port</dt>
                <dd className="tabular-nums">
                  {order.shippingCents === 0
                    ? 'Offerts'
                    : formatPrice(order.shippingCents)}
                </dd>
              </div>
              <div className="flex justify-between border-t border-line pt-1 text-base">
                <dt>Total</dt>
                <dd className="tabular-nums">{formatPrice(order.totalCents)}</dd>
              </div>
            </dl>
          </section>

          {/* --- Client et remise -------------------------------------------- */}
          <section className="grid gap-10 sm:grid-cols-2">
            <div>
              <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
                Client
              </h2>
              <p className="mt-3 text-sm">
                {order.customerName}
                <br />
                <a
                  href={`mailto:${order.email}`}
                  className="underline underline-offset-4"
                >
                  {order.email}
                </a>
                {order.phone && (
                  <>
                    <br />
                    {order.phone}
                  </>
                )}
              </p>
            </div>

            <div>
              <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
                {FULFILMENT_LABELS[order.fulfilment as keyof typeof FULFILMENT_LABELS] ??
                  order.fulfilment}
              </h2>

              <address className="mt-3 text-sm not-italic">
                {isPickup ? (
                  order.pickupPoint ? (
                    <>
                      <span className="block font-medium">
                        {order.pickupPoint.name}
                      </span>
                      {order.pickupPoint.addressLine1}
                      <br />
                      {order.pickupPoint.postalCode} {order.pickupPoint.city}
                    </>
                  ) : (
                    <span className="text-ink-soft">
                      Point de retrait supprimé depuis la commande.
                    </span>
                  )
                ) : (
                  <>
                    {order.shippingAddressLine1}
                    {order.shippingAddressLine2 && (
                      <>
                        <br />
                        {order.shippingAddressLine2}
                      </>
                    )}
                    <br />
                    {order.shippingPostalCode} {order.shippingCity}
                    <br />
                    France
                  </>
                )}
              </address>
            </div>
          </section>

          {/* --- Note interne ------------------------------------------------ */}
          <section>
            <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
              Note interne
            </h2>
            <p className="mt-2 text-sm text-ink-soft">
              Visible uniquement ici. Le client ne la voit jamais.
            </p>

            <form action={updateAdminNote} className="mt-3 space-y-3">
              <input type="hidden" name="id" value={order.id} />
              <label className="block">
                <span className="sr-only">Note interne sur la commande</span>
                <textarea
                  name="adminNote"
                  rows={3}
                  defaultValue={order.adminNote ?? ''}
                  maxLength={2000}
                  placeholder="Numéro de suivi, remarque du client, point à vérifier…"
                  className="w-full rounded-sm border border-line bg-cream px-3 py-2 text-sm"
                />
              </label>
              <Button type="submit" variant="secondary">
                Enregistrer la note
              </Button>
            </form>
          </section>
        </div>

        {/* --- Statut --------------------------------------------------------- */}
        <aside className="lg:sticky lg:top-8 lg:self-start">
          <div className="border border-line p-5">
            <h2 className="font-sans text-sm font-medium">Statut</h2>
            <p className="mt-2 text-sm text-ink-soft">
              Actuellement :{' '}
              <strong className="font-medium text-ink">
                {ORDER_STATUS_LABELS[order.status as OrderStatus] ?? order.status}
              </strong>
            </p>

            <form action={updateOrderStatus} className="mt-4 space-y-3">
              <input type="hidden" name="id" value={order.id} />

              <label className="block">
                <span className="block text-sm font-medium">Changer le statut</span>
                <select
                  name="status"
                  defaultValue={order.status}
                  className="mt-1.5 w-full rounded-sm border border-line bg-cream px-3 py-2 text-sm"
                >
                  {statuses.map((status) => (
                    <option key={status} value={status}>
                      {ORDER_STATUS_LABELS[status]}
                    </option>
                  ))}
                </select>
              </label>

              <Button type="submit" className="w-full">
                Mettre à jour
              </Button>
            </form>

            <p className="mt-4 text-xs text-ink-soft">
              Changer le statut ne prévient pas automatiquement le client et ne
              modifie pas le stock.
            </p>
          </div>

          {order.stripePaymentIntentId && (
            <p className="mt-4 text-xs text-ink-soft">
              Référence Stripe : {order.stripePaymentIntentId}
            </p>
          )}
        </aside>
      </div>
    </>
  );
}
