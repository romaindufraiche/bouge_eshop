import type { Metadata } from 'next';
import { CartView } from '@/app/(boutique)/panier/CartView';
import { Container } from '@/components/ui/Container';
import { buildMetadata } from '@/lib/seo';

// Le panier est propre à chaque visiteur : rien à indexer ici.
export const metadata: Metadata = buildMetadata({
  title: 'Votre panier',
  description: 'Les articles que vous avez sélectionnés.',
  path: '/panier',
  noIndex: true,
});

export default function CartPage() {
  return (
    <Container size="default">
      <div className="py-12 sm:py-16">
        <h1 className="text-4xl sm:text-5xl">Votre panier</h1>
        <div className="mt-10">
          <CartView />
        </div>
      </div>
    </Container>
  );
}
