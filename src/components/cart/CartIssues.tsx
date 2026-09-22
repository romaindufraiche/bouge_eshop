import type { CartIssue } from '@/lib/cart-server';

/**
 * Signale au client ce qui a changé dans son panier depuis qu'il l'a rempli :
 * article retiré du catalogue, rupture de stock, quantité réduite.
 * Le message est explicite, sans code d'erreur ni jargon.
 */
export function CartIssues({ issues }: { issues: CartIssue[] }) {
  if (issues.length === 0) return null;

  return (
    <div
      role="status"
      className="border-l-2 border-accent bg-sand px-4 py-3 text-sm"
    >
      <p className="font-medium">Votre panier a été mis à jour.</p>
      <ul className="mt-2 space-y-1 text-ink-soft">
        {issues.map((issue) => (
          <li key={`${issue.productId}-${issue.variantId ?? ''}-${issue.reason}`}>
            {describe(issue)}
          </li>
        ))}
      </ul>
    </div>
  );
}

function describe(issue: CartIssue): string {
  switch (issue.reason) {
    case 'unavailable':
      return `${issue.label} n'est plus disponible et a été retiré.`;
    case 'out-of-stock':
      return `${issue.label} est en rupture de stock et a été retiré.`;
    case 'reduced-quantity':
      return `${issue.label} : quantité ramenée à ${issue.keptQuantity}, c'est tout ce qu'il reste.`;
    case 'sold-elsewhere':
      return `${issue.label} est vendu par un revendeur : il ne peut pas être commandé ici et a été retiré.`;
  }
}
