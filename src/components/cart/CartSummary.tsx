import { formatPrice } from '@/lib/money';
import { centsUntilFreeShipping, SHIPPING } from '@/lib/shop-config';

/**
 * Récapitulatif chiffré du panier.
 * Sur la page panier, les frais de port ne sont pas encore connus : le mode de
 * remise (livraison ou retrait) est choisi à l'étape suivante.
 */
export function CartSummary({
  subtotalCents,
  shippingCents,
  children,
}: {
  subtotalCents: number;
  /** Null tant que le mode de remise n'est pas choisi. */
  shippingCents?: number | null;
  children?: React.ReactNode;
}) {
  const missing = centsUntilFreeShipping(subtotalCents);
  const totalCents = subtotalCents + (shippingCents ?? 0);

  return (
    <div className="border border-line p-5 sm:p-6">
      <h2 className="font-sans text-sm font-medium">Récapitulatif</h2>

      <dl className="mt-4 space-y-2 text-sm">
        <div className="flex justify-between">
          <dt className="text-ink-soft">Sous-total</dt>
          <dd className="tabular-nums">{formatPrice(subtotalCents)}</dd>
        </div>

        <div className="flex justify-between">
          <dt className="text-ink-soft">Livraison</dt>
          <dd className="tabular-nums">
            {shippingCents === null || shippingCents === undefined ? (
              <span className="text-ink-soft">Calculée à l&apos;étape suivante</span>
            ) : shippingCents === 0 ? (
              'Offerte'
            ) : (
              formatPrice(shippingCents)
            )}
          </dd>
        </div>

        <div className="flex justify-between border-t border-line pt-3 text-base">
          <dt>Total</dt>
          <dd className="tabular-nums">
            {shippingCents === null || shippingCents === undefined
              ? `à partir de ${formatPrice(subtotalCents)}`
              : formatPrice(totalCents)}
          </dd>
        </div>
      </dl>

      {missing !== null && SHIPPING.freeAboveCents !== null && (
        <p className="mt-4 text-sm text-ink-soft">
          Plus que {formatPrice(missing)} pour la livraison offerte.
        </p>
      )}

      {children && <div className="mt-6">{children}</div>}
    </div>
  );
}
