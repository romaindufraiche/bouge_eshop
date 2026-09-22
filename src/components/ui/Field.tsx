import type { ReactNode } from 'react';

/**
 * Champ de formulaire : libellé, aide facultative, message d'erreur.
 * Le message d'erreur est relié au champ par aria-describedby, pour qu'un
 * lecteur d'écran l'annonce au moment où l'utilisateur atteint le champ.
 */
export function Field({
  label,
  name,
  fieldId,
  hint,
  error,
  required = false,
  children,
}: {
  label: string;
  /** Nom du champ dans le formulaire. */
  name: string;
  /**
   * Identifiant HTML, quand le nom ne suffit pas à le rendre unique : c'est le
   * cas lorsque plusieurs formulaires coexistent sur une même page, chacun
   * avec un champ « name ».
   */
  fieldId?: string;
  hint?: string;
  error?: string;
  required?: boolean;
  children: (props: {
    id: string;
    name: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
    required?: boolean;
    className: string;
  }) => ReactNode;
}) {
  const id = `champ-${fieldId ?? name}`;
  const hintId = hint ? `${id}-aide` : undefined;
  const errorId = error ? `${id}-erreur` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium">
        {label}
        {!required && <span className="ml-1 text-ink-soft">(facultatif)</span>}
      </label>

      {hint && (
        <p id={hintId} className="mt-1 text-sm text-ink-soft">
          {hint}
        </p>
      )}

      <div className="mt-1.5">
        {children({
          id,
          name,
          'aria-describedby': describedBy,
          'aria-invalid': error ? true : undefined,
          required,
          className: `w-full rounded-sm border bg-cream px-3 py-2.5 text-base ${
            error ? 'border-accent' : 'border-line'
          }`,
        })}
      </div>

      {error && (
        <p id={errorId} className="mt-1.5 text-sm text-accent-deep">
          {error}
        </p>
      )}
    </div>
  );
}
