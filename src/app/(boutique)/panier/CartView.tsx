'use client';

import { CartIssues } from '@/components/cart/CartIssues';
import { CartLines } from '@/components/cart/CartLines';
import { CartSummary } from '@/components/cart/CartSummary';
import { useResolvedCart } from '@/components/cart/useResolvedCart';
import { ButtonLink } from '@/components/ui/Button';

export function CartView() {
  const { cart, isLoading, hasFailed } = useResolvedCart();

  if (isLoading) {
    return <p className="py-12 text-ink-soft">Chargement du panier…</p>;
  }

  if (hasFailed) {
    return (
      <p className="py-12 text-ink-soft">
        Le panier n&apos;a pas pu être chargé. Vérifiez votre connexion et
        rechargez la page.
      </p>
    );
  }

  if (!cart || cart.isEmpty) {
    return (
      <div className="py-12">
        {cart && <CartIssues issues={cart.issues} />}
        <p className="mt-6 text-ink-soft">Votre panier est vide.</p>
        <div className="mt-6">
          <ButtonLink href="/boutique">Voir le catalogue</ButtonLink>
        </div>
      </div>
    );
  }

  return (
    <div className="grid gap-10 lg:grid-cols-[1fr_20rem] lg:gap-14">
      <div>
        {cart.issues.length > 0 && (
          <div className="mb-6">
            <CartIssues issues={cart.issues} />
          </div>
        )}
        <CartLines lines={cart.lines} />
      </div>

      <aside className="lg:sticky lg:top-28 lg:self-start">
        <CartSummary subtotalCents={cart.subtotalCents}>
          <div className="space-y-3">
            <ButtonLink href="/commande" variant="accent" className="w-full" size="lg">
              Commander
            </ButtonLink>
            <ButtonLink href="/boutique" variant="ghost" className="w-full">
              Continuer mes achats
            </ButtonLink>
          </div>
        </CartSummary>
      </aside>
    </div>
  );
}
