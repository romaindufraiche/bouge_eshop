import { sellerName } from '@/lib/external';

/**
 * Bloc d'achat d'un produit vendu par un tiers.
 *
 * Aucun prix n'est affiché : il est fixé par le revendeur et peut changer
 * sans que nous le sachions. Annoncer un montant que le client ne retrouverait
 * pas au moment de payer serait pire que de ne rien annoncer.
 */
export function ExternalPurchase({
  product,
}: {
  product: {
    externalUrl: string | null;
    externalLabel: string | null;
    availableInStore: boolean;
  };
}) {
  if (!product.externalUrl) return null;

  const vendeur = sellerName(product);

  return (
    <div className="space-y-5">
      <p className="text-lg text-ink-soft">
        Ce modèle est vendu par {vendeur}.
      </p>

      <a
        href={product.externalUrl}
        target="_blank"
        // `noopener` empêche la page ouverte d'accéder à la nôtre,
        // `noreferrer` évite de lui transmettre la page d'origine.
        rel="noopener noreferrer"
        className="inline-flex items-center justify-center gap-2 rounded-control border border-accent-deep bg-accent-deep px-7 py-3.5 text-base font-semibold tracking-wide text-white transition-colors hover:border-ink hover:bg-ink"
      >
        Acheter sur {vendeur}
        <span aria-hidden="true">→</span>
        <span className="sr-only">(nouvel onglet)</span>
      </a>

      {product.availableInStore && (
        <p className="flex items-start gap-2 rounded-surface bg-sand px-4 py-3 text-sm">
          <span aria-hidden="true">📍</span>
          <span>
            <strong className="font-semibold">Disponible en magasin.</strong>{' '}
            Passez l&apos;essayer et repartez avec, sans frais de port ni
            délai de livraison.
          </span>
        </p>
      )}
    </div>
  );
}
