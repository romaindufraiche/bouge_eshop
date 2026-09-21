/**
 * Lecture des variables d'environnement.
 *
 * On préfère échouer avec un message explicite au démarrage plutôt que de
 * laisser une erreur cryptique surgir au milieu d'un paiement.
 */

export function requireEnv(name: string): string {
  const value = process.env[name];

  if (!value || value.trim() === '') {
    throw new Error(
      `Variable d'environnement manquante : ${name}. ` +
        'Copiez .env.example en .env et renseignez cette valeur.',
    );
  }

  return value;
}

/** Vrai si Stripe est configuré. Permet d'afficher un message clair côté UI. */
export function isStripeConfigured(): boolean {
  return Boolean(process.env.STRIPE_SECRET_KEY?.trim());
}
