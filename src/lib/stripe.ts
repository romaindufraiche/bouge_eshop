import Stripe from 'stripe';
import { requireEnv } from '@/lib/env';

let client: Stripe | null = null;

/**
 * Client Stripe, instancié à la première utilisation.
 *
 * L'instanciation est différée pour que le site puisse être construit et les
 * pages publiques servies même sans clé Stripe configurée : seul le tunnel de
 * paiement en a besoin.
 */
export function getStripe(): Stripe {
  if (!client) {
    client = new Stripe(requireEnv('STRIPE_SECRET_KEY'), {
      // Identifie l'intégration dans les journaux du tableau de bord Stripe.
      appInfo: { name: 'BOUGE. e-shop' },
    });
  }

  return client;
}
