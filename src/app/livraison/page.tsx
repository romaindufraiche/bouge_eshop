import type { Metadata } from 'next';
import { LegalPage } from '@/components/ui/LegalPage';
import { formatPrice } from '@/lib/money';
import { getActivePickupPoints } from '@/lib/queries';
import { buildMetadata } from '@/lib/seo';
import { SHIPPING } from '@/lib/shop-config';

// Les pages publiques sont régénérées au maximum toutes les 5 minutes :
// un prix ou une promotion modifiés apparaissent sans redéploiement.
export const revalidate = 300;

export const metadata: Metadata = buildMetadata({
  title: 'Livraison et retrait',
  description:
    'Modes de livraison en France, frais de port, seuil de livraison offerte et points de retrait.',
  path: '/livraison',
});

export default async function LivraisonPage() {
  const pickupPoints = await getActivePickupPoints();

  return (
    <LegalPage title="Livraison et retrait">
      <section>
        <h2>Livraison en France</h2>
        <p>
          Frais de port : {formatPrice(SHIPPING.flatRateCents)}
          {SHIPPING.freeAboveCents !== null && (
            <>
              , offerts à partir de {formatPrice(SHIPPING.freeAboveCents)}{' '}
              d&apos;achat
            </>
          )}
          . Les commandes partent sous 48 heures ouvrées.
        </p>
      </section>

      {pickupPoints.length > 0 && (
        <section>
          <h2>Retrait sur place</h2>
          <p>
            Sans frais. Vous recevez un courriel dès que la commande est prête.
          </p>
          <ul className="!list-none !pl-0 space-y-4">
            {pickupPoints.map((point) => (
              <li key={point.id} className="border border-line p-4">
                <p className="font-medium text-ink">{point.name}</p>
                <p className="mt-1 text-sm">
                  {point.addressLine1}
                  {point.addressLine2 && (
                    <>
                      <br />
                      {point.addressLine2}
                    </>
                  )}
                  <br />
                  {point.postalCode} {point.city}
                </p>
                {point.hours && <p className="mt-1 text-sm">{point.hours}</p>}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section>
        <h2>Retours</h2>
        <p>
          Quatorze jours pour changer d&apos;avis. Les conditions détaillées
          figurent dans les conditions générales de vente.
        </p>
      </section>
    </LegalPage>
  );
}
