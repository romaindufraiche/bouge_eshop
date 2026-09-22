'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useCart } from '@/components/cart/CartProvider';
import { formatPrice } from '@/lib/money';
import type { ResolvedCartLine } from '@/lib/cart-server';

/** Liste des articles du panier, avec réglage de quantité et retrait. */
export function CartLines({
  lines,
  /** En lecture seule dans le récapitulatif du tunnel de commande. */
  editable = true,
}: {
  lines: ResolvedCartLine[];
  editable?: boolean;
}) {
  const { setQuantity, remove } = useCart();

  return (
    <ul className="divide-y divide-line border-y border-line">
      {lines.map((line) => (
        <li
          key={`${line.productId}-${line.variantId ?? ''}`}
          className="flex gap-4 py-5"
        >
          <Link
            href={`/produit/${line.slug}`}
            className="relative aspect-square w-20 shrink-0 overflow-hidden rounded-surface bg-sand sm:w-24"
          >
            {line.imageUrl ? (
              <Image
                src={line.imageUrl}
                alt={line.name}
                fill
                sizes="96px"
                className="object-cover"
              />
            ) : null}
          </Link>

          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <Link
              href={`/produit/${line.slug}`}
              className="text-base hover:underline hover:underline-offset-4"
            >
              {line.name}
            </Link>
            {line.variantLabel && (
              <p className="text-sm text-ink-soft">{line.variantLabel}</p>
            )}

            <p className="text-sm tabular-nums text-ink-soft">
              {formatPrice(line.unitPriceCents)} l&apos;unité
              {line.compareAtCents !== null && (
                <s className="ml-2">{formatPrice(line.compareAtCents)}</s>
              )}
            </p>

            {editable ? (
              <div className="mt-2 flex items-center gap-4">
                <label className="flex items-center gap-2 text-sm">
                  <span className="sr-only">Quantité pour {line.name}</span>
                  <select
                    value={line.quantity}
                    onChange={(event) =>
                      setQuantity(
                        { productId: line.productId, variantId: line.variantId },
                        Number(event.target.value),
                      )
                    }
                    className="rounded-sm border border-line bg-cream px-2 py-1.5 tabular-nums"
                  >
                    {Array.from(
                      { length: Math.max(line.availableStock, 1) },
                      (_, index) => index + 1,
                    ).map((value) => (
                      <option key={value} value={value}>
                        {value}
                      </option>
                    ))}
                  </select>
                </label>

                <button
                  type="button"
                  onClick={() =>
                    remove({ productId: line.productId, variantId: line.variantId })
                  }
                  className="text-sm text-ink-soft underline underline-offset-4 hover:text-ink"
                >
                  Retirer
                </button>
              </div>
            ) : (
              <p className="mt-1 text-sm text-ink-soft">
                Quantité : {line.quantity}
              </p>
            )}
          </div>

          <p className="shrink-0 tabular-nums">
            {formatPrice(line.lineTotalCents)}
          </p>
        </li>
      ))}
    </ul>
  );
}
