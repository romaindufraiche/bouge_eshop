import type { Metadata } from 'next';
import Link from 'next/link';
import { ClearCartOnMount } from '@/components/cart/ClearCartOnMount';
import { ButtonLink } from '@/components/ui/Button';
import { Container } from '@/components/ui/Container';
import { FULFILMENT, ORDER_STATUS } from '@/lib/constants';
import { formatPrice } from '@/lib/money';
import { prisma } from '@/lib/prisma';
import { buildMetadata } from '@/lib/seo';
import { getStripe } from '@/lib/stripe';

export const metadata: Metadata = buildMetadata({
  title: 'Commande confirmée',
  description: 'Récapitulatif de votre commande.',
  path: '/commande/confirmation',
  noIndex: true,
});

// Cette page dépend entièrement du paramètre renvoyé par Stripe.
export const dynamic = 'force-dynamic';

type PageProps = { searchParams: Promise<{ session_id?: string }> };

export default async function ConfirmationPage({ searchParams }: PageProps) {
  const { session_id: sessionId } = await searchParams;

  if (!sessionId) {
    return (
      <Message
        title="Commande introuvable"
        body="Le lien de confirmation est incomplet. Si vous avez été débité, écrivez-nous : nous retrouverons votre commande."
      />
    );
  }

  // On interroge Stripe directement : la page peut être atteinte avant que le
  // webhook n'ait fini son travail, et c'est Stripe qui sait si le paiement a
  // abouti.
  let isPaid = false;
  try {
    const session = await getStripe().checkout.sessions.retrieve(sessionId);
    isPaid = session.payment_status === 'paid';
  } catch (error) {
    console.error('Session Stripe illisible', error);
  }

  const order = await prisma.order.findUnique({
    where: { stripeSessionId: sessionId },
    include: { items: true, pickupPoint: true },
  });

  if (!order) {
    return (
      <Message
        title="Commande introuvable"
        body="Nous n'avons pas retrouvé cette commande. Si vous avez été débité, écrivez-nous avec la date et le montant : nous la retrouverons."
      />
    );
  }

  if (!isPaid && order.status === ORDER_STATUS.PENDING) {
    return (
      <Message
        title="Paiement en cours de vérification"
        body={`Votre commande ${order.reference} est enregistrée, mais le paiement n'est pas encore confirmé. Rechargez cette page dans quelques instants ; vous recevrez un courriel dès la confirmation.`}
      />
    );
  }

  return (
    <>
      <ClearCartOnMount />

      <Container size="default">
        <div className="py-12 sm:py-16">
          <p className="text-xs uppercase tracking-widest text-ink-soft">
            Commande {order.reference}
          </p>
          <h1 className="mt-2 text-4xl sm:text-5xl">Merci, c&apos;est confirmé.</h1>
          <p className="mt-4 max-w-xl text-ink-soft">
            Un récapitulatif part à l&apos;instant sur {order.email}.{' '}
            {order.fulfilment === FULFILMENT.PICKUP
              ? 'Nous vous prévenons dès que la commande est prête à être retirée.'
              : 'Votre commande part sous 48 heures ouvrées.'}
          </p>

          <div className="mt-12 grid gap-10 sm:grid-cols-2">
            {/* --- Mode de remise ------------------------------------------ */}
            <section>
              <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
                {order.fulfilment === FULFILMENT.PICKUP
                  ? 'Retrait sur place'
                  : 'Livraison'}
              </h2>

              {order.fulfilment === FULFILMENT.PICKUP ? (
                <address className="mt-3 text-sm not-italic">
                  {order.pickupPoint ? (
                    <>
                      <span className="block font-medium">
                        {order.pickupPoint.name}
                      </span>
                      {order.pickupPoint.addressLine1}
                      <br />
                      {order.pickupPoint.postalCode} {order.pickupPoint.city}
                      {order.pickupPoint.hours && (
                        <>
                          <br />
                          <span className="text-ink-soft">
                            {order.pickupPoint.hours}
                          </span>
                        </>
                      )}
                    </>
                  ) : (
                    'Point de retrait à confirmer.'
                  )}
                </address>
              ) : (
                <address className="mt-3 text-sm not-italic">
                  {order.customerName}
                  <br />
                  {order.shippingAddressLine1}
                  {order.shippingAddressLine2 && (
                    <>
                      <br />
                      {order.shippingAddressLine2}
                    </>
                  )}
                  <br />
                  {order.shippingPostalCode} {order.shippingCity}
                </address>
              )}
            </section>

            {/* --- Totaux --------------------------------------------------- */}
            <section>
              <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
                Montant
              </h2>
              <dl className="mt-3 space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-ink-soft">Sous-total</dt>
                  <dd className="tabular-nums">{formatPrice(order.subtotalCents)}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-ink-soft">Livraison</dt>
                  <dd className="tabular-nums">
                    {order.shippingCents === 0
                      ? 'Offerte'
                      : formatPrice(order.shippingCents)}
                  </dd>
                </div>
                <div className="flex justify-between border-t border-line pt-2 text-base">
                  <dt>Total payé</dt>
                  <dd className="tabular-nums">{formatPrice(order.totalCents)}</dd>
                </div>
              </dl>
            </section>
          </div>

          {/* --- Articles --------------------------------------------------- */}
          <section className="mt-12">
            <h2 className="font-sans text-sm font-medium uppercase tracking-widest text-ink-soft">
              Articles
            </h2>
            <ul className="mt-4 divide-y divide-line border-y border-line">
              {order.items.map((item) => (
                <li key={item.id} className="flex justify-between gap-4 py-4 text-sm">
                  <span>
                    {item.productName}
                    {item.variantLabel && (
                      <span className="text-ink-soft"> — {item.variantLabel}</span>
                    )}
                    <span className="text-ink-soft"> × {item.quantity}</span>
                  </span>
                  <span className="shrink-0 tabular-nums">
                    {formatPrice(item.lineTotalCents)}
                  </span>
                </li>
              ))}
            </ul>
          </section>

          <div className="mt-10 flex flex-wrap gap-3">
            <ButtonLink href="/boutique">Continuer mes achats</ButtonLink>
          </div>
        </div>
      </Container>
    </>
  );
}

function Message({ title, body }: { title: string; body: string }) {
  return (
    <Container size="narrow">
      <div className="py-24 text-center">
        <h1 className="text-3xl sm:text-4xl">{title}</h1>
        <p className="mt-4 text-ink-soft">{body}</p>
        <div className="mt-8 flex justify-center gap-3">
          <ButtonLink href="/boutique">Voir le catalogue</ButtonLink>
          <Link
            href="/mentions-legales"
            className="self-center text-sm underline underline-offset-4"
          >
            Nous contacter
          </Link>
        </div>
      </div>
    </Container>
  );
}
