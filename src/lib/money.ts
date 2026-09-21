/**
 * Manipulation des montants.
 *
 * Tous les prix circulent en CENTIMES (entiers) dans l'application et la base.
 * Les conversions en euros n'ont lieu qu'à l'affichage et dans les formulaires.
 */

const EUR = new Intl.NumberFormat('fr-FR', {
  style: 'currency',
  currency: 'EUR',
});

/** 1490 -> "14,90 €" */
export function formatPrice(cents: number): string {
  return EUR.format(cents / 100);
}

/** 1490 -> "14.90" (valeur d'un <input type="number"> en euros) */
export function centsToEuroInput(cents: number): string {
  return (cents / 100).toFixed(2);
}

/**
 * "14,90" ou "14.90" -> 1490.
 * Renvoie null si la saisie n'est pas un montant valide, pour que l'appelant
 * affiche un message clair plutôt qu'enregistrer NaN.
 */
export function euroInputToCents(value: string): number | null {
  const normalised = value.trim().replace(',', '.');
  if (normalised === '') return null;

  const euros = Number(normalised);
  if (!Number.isFinite(euros) || euros < 0) return null;

  return Math.round(euros * 100);
}
