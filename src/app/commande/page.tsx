import type { Metadata } from 'next';
import { CheckoutView } from '@/app/commande/CheckoutView';
import { Container } from '@/components/ui/Container';
import { isStripeConfigured } from '@/lib/env';
import { getActivePickupPoints } from '@/lib/queries';
import { buildMetadata } from '@/lib/seo';

export const metadata: Metadata = buildMetadata({
  title: 'Votre commande',
  description: 'Coordonnées, mode de livraison et paiement.',
  path: '/commande',
  noIndex: true,
});

export default async function CheckoutPage() {
  const pickupPoints = await getActivePickupPoints();

  return (
    <Container size="default">
      <div className="py-12 sm:py-16">
        <h1 className="text-4xl sm:text-5xl">Votre commande</h1>

        {!isStripeConfigured() && (
          <p className="mt-6 border-l-2 border-accent bg-sand px-4 py-3 text-sm">
            <strong className="font-medium">Paiement non configuré.</strong>{' '}
            Renseignez <code>STRIPE_SECRET_KEY</code> dans le fichier{' '}
            <code>.env</code> pour activer le règlement par carte.
          </p>
        )}

        <div className="mt-10">
          <CheckoutView
            pickupPoints={pickupPoints.map((point) => ({
              id: point.id,
              name: point.name,
              addressLine1: point.addressLine1,
              addressLine2: point.addressLine2,
              postalCode: point.postalCode,
              city: point.city,
              hours: point.hours,
            }))}
          />
        </div>
      </div>
    </Container>
  );
}
