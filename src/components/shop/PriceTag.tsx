import { formatPrice } from '@/lib/money';
import type { EffectivePrice } from '@/lib/pricing';

/**
 * Affichage d'un prix, avec le prix barré et la remise quand une promotion
 * est en cours.
 */
export function PriceTag({
  price,
  size = 'md',
}: {
  price: EffectivePrice;
  size?: 'sm' | 'md' | 'lg';
}) {
  const currentSize = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-2xl',
  }[size];

  if (!price.onSale || price.compareAtCents === null) {
    return <span className={`${currentSize} tabular-nums`}>{formatPrice(price.cents)}</span>;
  }

  return (
    <span className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
      <span className={`${currentSize} tabular-nums text-accent`}>
        {formatPrice(price.cents)}
      </span>
      <s className="text-sm tabular-nums text-ink-soft">
        {formatPrice(price.compareAtCents)}
      </s>
      {price.discountPercent !== null && (
        <span className="border border-accent px-1.5 py-0.5 text-[0.6875rem] font-medium tracking-wide text-accent">
          −{price.discountPercent} %
        </span>
      )}
    </span>
  );
}
